from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import mediapipe as mp
import os

app = Flask(__name__)

# CORS Configuration
CORS(app, resources={
    r"/*": {
        "origins": "*",
        "methods": ["GET", "POST", "OPTIONS"],
        "allow_headers": ["Content-Type"]
    }
})

# =====================================================
# MEDIAPIPE SETUP
# =====================================================
mp_pose = mp.solutions.pose
pose = mp_pose.Pose(static_image_mode=True, min_detection_confidence=0.5)

# =====================================================
# TENSORFLOW MODEL SETUP (OPTIONAL)
# =====================================================
USE_ML_MODEL = False
try:
    import tensorflow as tf
    MODEL_PATH = "model/model_height.keras"
    
    if os.path.exists(MODEL_PATH):
        model = tf.keras.models.load_model(MODEL_PATH, compile=False)
        USE_ML_MODEL = True
        print("✅ TensorFlow Model loaded successfully")
    else:
        print(f"⚠️ Model tidak ditemukan: {MODEL_PATH}")
        print("💡 Menggunakan MediaPipe fallback")
except Exception as e:
    print(f"⚠️ TensorFlow tidak tersedia: {e}")
    print("💡 Menggunakan MediaPipe fallback")

# =====================================================
# HELPER FUNCTIONS
# =====================================================
def read_image(file):
    """Baca file gambar dari request"""
    file_bytes = np.frombuffer(file.read(), np.uint8)
    return cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)

def precheck_image(image):
    """Validasi kondisi gambar sebelum prediksi - return 3 kriteria terpisah"""
    try:
        if image is None:
            return {
                'cahaya_ok': False,
                'posisi_ok': False,
                'jarak_ok': False,
                'cahaya_message': 'Gambar tidak terbaca',
                'posisi_message': 'Gambar tidak terbaca',
                'jarak_message': 'Gambar tidak terbaca'
            }

        h, w = image.shape[:2]
        image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        results = pose.process(image_rgb)

        if not results.pose_landmarks:
            return {
                'cahaya_ok': False,
                'posisi_ok': False,
                'jarak_ok': False,
                'cahaya_message': 'Pose tidak terdeteksi',
                'posisi_message': 'Pose anak tidak terdeteksi',
                'jarak_message': 'Pose tidak terdeteksi'
            }

        lm = results.pose_landmarks.landmark
        
        nose = lm[mp_pose.PoseLandmark.NOSE]
        l_sh = lm[mp_pose.PoseLandmark.LEFT_SHOULDER]
        r_sh = lm[mp_pose.PoseLandmark.RIGHT_SHOULDER]
        l_ankle = lm[mp_pose.PoseLandmark.LEFT_ANKLE]

        # INISIALISASI HASIL
        result = {
            'cahaya_ok': False,
            'posisi_ok': False,
            'jarak_ok': False,
            'cahaya_message': '',
            'posisi_message': '',
            'jarak_message': ''
        }

        # 1. CEK CAHAYA
        brightness = np.mean(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
        if brightness < 50:
            result['cahaya_message'] = 'Cahaya terlalu gelap, tambahkan pencahayaan'
        elif brightness > 200:
            result['cahaya_message'] = 'Cahaya terlalu terang, kurangi pencahayaan'
        else:
            result['cahaya_ok'] = True
            result['cahaya_message'] = 'Cahaya cukup'

        # 2. CEK POSISI (Tengah + Tegap)
        avg_x = (nose.x + l_sh.x + r_sh.x) / 3
        shoulder_diff = abs(l_sh.y - r_sh.y)
        
        if avg_x < 0.4:
            result['posisi_message'] = 'Geser anak ke kanan'
        elif avg_x > 0.6:
            result['posisi_message'] = 'Geser anak ke kiri'
        elif shoulder_diff > 0.05:
            result['posisi_message'] = 'Posisikan bahu sejajar (tegak lurus)'
        else:
            result['posisi_ok'] = True
            result['posisi_message'] = 'Posisi sudah tepat'

        # 3. CEK JARAK (Jarak kamera + Tinggi kamera)
        body_ratio = abs((l_ankle.y - nose.y) * h) / h
        nose_y_pos = nose.y * h
        
        if body_ratio < 0.6:
            result['jarak_message'] = 'Jarak terlalu jauh, dekatkan hingga ±2 meter'
        elif body_ratio > 0.85:
            result['jarak_message'] = 'Jarak terlalu dekat, jauhkan hingga ±2 meter'
        elif nose_y_pos < h * 0.15:
            result['jarak_message'] = 'Naikkan kamera hingga ±1 meter dari lantai'
        elif nose_y_pos > h * 0.85:
            result['jarak_message'] = 'Turunkan kamera hingga ±1 meter dari lantai'
        else:
            result['jarak_ok'] = True
            result['jarak_message'] = 'Jarak sudah sesuai'

        return result
    
    except Exception as e:
        print(f"❌ Precheck exception: {str(e)}")
        return {
            'cahaya_ok': False,
            'posisi_ok': False,
            'jarak_ok': False,
            'cahaya_message': f'Error: {str(e)}',
            'posisi_message': f'Error: {str(e)}',
            'jarak_message': f'Error: {str(e)}'
        }

def predict_height_with_model(image):
    """Prediksi menggunakan ML Model"""
    try:
        img = cv2.resize(image, (224, 224))
        img = img / 255.0
        img = np.expand_dims(img, axis=0)
        tinggi = model.predict(img, verbose=0)[0][0]
        return round(float(tinggi), 1)
    except Exception as e:
        print(f"❌ Model prediction error: {e}")
        return None

def predict_height_with_mediapipe(image):
    """Prediksi menggunakan MediaPipe (fallback)"""
    try:
        h, w = image.shape[:2]
        image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        results = pose.process(image_rgb)
        
        if not results.pose_landmarks:
            print("❌ Pose tidak terdeteksi")
            return None
        
        lm = results.pose_landmarks.landmark
        
        # Hitung dari hidung ke ankle
        nose = lm[mp_pose.PoseLandmark.NOSE]
        left_ankle = lm[mp_pose.PoseLandmark.LEFT_ANKLE]
        right_ankle = lm[mp_pose.PoseLandmark.RIGHT_ANKLE]
        
        # Ambil rata-rata ankle
        ankle_y = (left_ankle.y + right_ankle.y) / 2
        
        # Hitung pixel height
        pixel_height = abs(ankle_y - nose.y) * h
        
        # Konversi ke cm dengan formula kalibrasi
        body_ratio = pixel_height / h
        
        if body_ratio < 0.5:
            tinggi_cm = 80  # Terlalu jauh
        elif body_ratio > 0.9:
            tinggi_cm = 85  # Terlalu dekat
        else:
            # Formula kalibrasi: asumsi body_ratio 0.75 = tinggi 110cm
            tinggi_cm = (body_ratio / 0.75) * 110
        
        return round(tinggi_cm, 1)
    
    except Exception as e:
        print(f"❌ MediaPipe prediction error: {e}")
        return None

def predict_height(image):
    """Main function untuk prediksi tinggi"""
    if image is None:
        return 0
    
    # Coba dengan ML model dulu jika tersedia
    if USE_ML_MODEL:
        tinggi = predict_height_with_model(image)
        if tinggi is not None and tinggi > 0:
            return tinggi
        print("⚠️ Model prediction failed, using MediaPipe fallback")
    
    # Fallback ke MediaPipe
    tinggi = predict_height_with_mediapipe(image)
    
    if tinggi is None or tinggi <= 0:
        print("❌ All prediction methods failed")
        return 0
    
    return tinggi

# =====================================================
# API ROUTES
# =====================================================
@app.route("/", methods=["GET"])
def home():
    """Endpoint untuk cek server hidup"""
    return jsonify({
        "status": "online",
        "message": "Flask AI Server Running",
        "model_loaded": USE_ML_MODEL,
        "endpoints": ["/precheck", "/predict"]
    })

@app.route("/precheck", methods=["POST", "OPTIONS"])
def scan_precheck():
    """Endpoint untuk validasi posisi sebelum scan"""
    
    # Handle preflight OPTIONS request
    if request.method == "OPTIONS":
        return jsonify({"status": "ok"}), 200
    
    try:
        print(f"📥 Precheck request received from {request.remote_addr}")
        print(f"📋 Files in request: {list(request.files.keys())}")
        
        # Cek apakah ada file
        if 'file' not in request.files:
            return jsonify({
                "precheck": False,
                "message": "File tidak ditemukan"
            }), 400

        file = request.files['file']
        
        # Validasi file kosong
        if file.filename == '':
            return jsonify({
                "precheck": False,
                "message": "File kosong"
            }), 400

        # Baca gambar
        image = read_image(file)
        
        if image is None:
            return jsonify({
                "precheck": False,
                "message": "Gambar tidak dapat dibaca"
            }), 400

        # Jalankan precheck
        result = precheck_image(image)
        
        print(f"✅ Precheck result:")
        print(f"   Cahaya: {result['cahaya_ok']} - {result['cahaya_message']}")
        print(f"   Posisi: {result['posisi_ok']} - {result['posisi_message']}")
        print(f"   Jarak: {result['jarak_ok']} - {result['jarak_message']}")

        return jsonify(result)

    except Exception as e:
        import traceback
        print(f"❌ Precheck Error: {str(e)}")
        print(traceback.format_exc())
        return jsonify({
            "precheck": False,
            "message": f"Error: {str(e)}"
        }), 500

@app.route("/predict", methods=["POST", "OPTIONS"])
def scan_predict():
    """Endpoint untuk prediksi tinggi badan"""
    
    # Handle preflight OPTIONS request
    if request.method == "OPTIONS":
        return jsonify({"status": "ok"}), 200
    
    try:
        print(f"📥 Predict request received from {request.remote_addr}")
        print(f"📋 Files in request: {list(request.files.keys())}")
        
        # Cek apakah ada file
        if 'file' not in request.files:
            return jsonify({
                "success": False,
                "tinggi": 0,
                "message": "File tidak ditemukan"
            }), 400

        file = request.files['file']
        
        # Validasi file kosong
        if file.filename == '':
            return jsonify({
                "success": False,
                "tinggi": 0,
                "message": "File kosong"
            }), 400

        # Baca gambar
        image = read_image(file)
        
        if image is None:
            return jsonify({
                "success": False,
                "tinggi": 0,
                "message": "Gambar tidak dapat dibaca"
            }), 400

        # Prediksi tinggi
        tinggi = predict_height(image)
        
        print(f"✅ Predict result: {tinggi} cm")

        return jsonify({
            "success": True,
            "tinggi": float(tinggi) if tinggi else 0
        })

    except Exception as e:
        import traceback
        print(f"❌ Predict Error: {str(e)}")
        print(traceback.format_exc())
        return jsonify({
            "success": False,
            "tinggi": 0,
            "message": f"Error: {str(e)}"
        }), 500

# =====================================================
# MAIN
# =====================================================
if __name__ == "__main__":
    print("\n" + "="*60)
    print("🚀 Starting Flask AI Server...")
    print("="*60)
    print(f"📍 Server URL: http://127.0.0.1:5000")
    print(f"🤖 ML Model: {'✅ Loaded' if USE_ML_MODEL else '⚠️ Using MediaPipe fallback'}")
    print(f"✅ Endpoints:")
    print(f"   - GET  /          (status check)")
    print(f"   - POST /precheck  (validasi posisi)")
    print(f"   - POST /predict   (prediksi tinggi)")
    print(f"\n⚠️  Press CTRL+C to stop server")
    print("="*60 + "\n")
    
port = int(os.environ.get('PORT', 5000))
app.run(debug=True, host='0.0.0.0', port=port)