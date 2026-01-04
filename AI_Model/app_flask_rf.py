from flask import Flask, request, jsonify
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import accuracy_score
import joblib, os

app = Flask(__name__)

# ===================== 1️⃣ Baca Dataset =====================
file_path = 'dataset_rf_stunting.xlsx'

if not os.path.exists(file_path):
    raise FileNotFoundError("❌ File dataset_rf_stunting.xlsx tidak ditemukan!")

df = pd.read_excel(file_path, sheet_name='DataInput')

# Bersihkan data
df['Status_Stunting'] = df['Status_Stunting'].astype(str).str.strip().str.title()
df['Jenis_Kelamin'] = df['Jenis_Kelamin'].map({'Laki-Laki': 1, 'Laki-laki': 1, 'Perempuan': 0})
for col in ['Usia_Bulan', 'Jenis_Kelamin', 'Tinggi_Badan_cm', 'Berat_Badan_kg']:
    df[col] = pd.to_numeric(df[col], errors='coerce')
df = df.dropna(subset=['Usia_Bulan', 'Jenis_Kelamin', 'Tinggi_Badan_cm', 'Berat_Badan_kg'])

# ===================== 2️⃣ Latih Model Random Forest =====================
X = df[['Usia_Bulan', 'Jenis_Kelamin', 'Tinggi_Badan_cm', 'Berat_Badan_kg']]
y = df['Status_Stunting']

le = LabelEncoder()
y_encoded = le.fit_transform(y)

X_train, X_test, y_train, y_test = train_test_split(X, y_encoded, test_size=0.2, random_state=42)
model = RandomForestClassifier(
    n_estimators=200,
    max_depth=10,
    class_weight='balanced',
    random_state=42
)
model.fit(X_train, y_train)

akurasi = accuracy_score(y_test, model.predict(X_test)) * 100
print(f"✅ Model Random Forest dilatih (akurasi: {akurasi:.2f}%)")

joblib.dump(model, 'model_rf_stunting.pkl')
joblib.dump(le, 'label_encoder.pkl')

# ===================== 3️⃣ Baca Tabel WHO Z-Score =====================
z_laki = pd.read_excel(file_path, sheet_name='Anak Laki-laki', skiprows=2)
z_perempuan = pd.read_excel(file_path, sheet_name='Anak Perempuan', skiprows=2)
for zdf in [z_laki, z_perempuan]:
    zdf.rename(columns=lambda c: str(c).strip(), inplace=True)

# ===================== 4️⃣ Hitung Z-Score Berdasarkan WHO =====================
def hitung_zscore(umur, tinggi, jenis_kelamin):
    ref = z_laki if 'laki' in jenis_kelamin else z_perempuan
    usia_terdekat = ref.iloc[(ref['USIA'] - umur).abs().argsort()[:1]]
    if usia_terdekat.empty:
        return None

    row = usia_terdekat.iloc[0]
    batas = {
        -3: row['-SD 3'],
        -2: row['-SD 2'],
        -1: row['-SD 1'],
         0: row['Median'],
         1: row['+SD 1'],
         2: row['+SD 2'],
         3: row['+SD 3']
    }

    tinggi = float(tinggi)
    if tinggi <= batas[-3]:
        return -3.5
    elif tinggi >= batas[3]:
        return 3.5

    keys = sorted(batas.keys())
    for i in range(len(keys) - 1):
        k1, k2 = keys[i], keys[i + 1]
        v1, v2 = batas[k1], batas[k2]
        if v1 <= tinggi <= v2:
            z = k1 + (tinggi - v1) * (k2 - k1) / (v2 - v1)
            return round(z, 2)
    return None

# ===================== 5️⃣ Interpretasi Z-Score (Sesuai PMK No. 2/2020) =====================
def interpretasi_zscore(z):
    if z is None:
        return "Tidak Diketahui", 0, "Data tidak mencukupi untuk interpretasi Z-score."

    # Berdasarkan PMK No. 2 Tahun 2020, Pasal 4 ayat (3)
    if z >= -2:
        return "Normal", 10, (
            "Anak memiliki tinggi badan sesuai umur dan termasuk kategori normal "
            "berdasarkan Standar Antropometri Anak (PMK No. 2 Tahun 2020)."
        )
    elif z >= -3:
        return "Stunted", 65, (
            "Anak termasuk kategori pendek (stunted) "
            "menurut PMK No. 2 Tahun 2020, yaitu Z-score antara -3 SD hingga kurang dari -2 SD. "
            "Hal ini menunjukkan adanya gangguan pertumbuhan kronis yang perlu pemantauan gizi."
        )
    else:
        return "Severely Stunted", 90, (
            "Anak tergolong sangat pendek (severely stunted) dengan Z-score < -3 SD "
            "sesuai ketentuan PMK No. 2 Tahun 2020. "
            "Segera rujuk ke fasilitas kesehatan untuk evaluasi dan tata laksana gizi lanjut."
        )

# ===================== 6️⃣ Saran Otomatis =====================
def get_saran_otomatis(status, z, risiko):
    if status == "Normal":
        warna = "#7DDCD3"
        saran = (
            f"Risiko Stunting: {risiko:.1f}% (Normal)\n"
            f"Nilai Z-Score: {z}\n\n"
            "Pertumbuhan anak normal sesuai Standar Antropometri Anak (PMK No. 2 Tahun 2020). "
            "Tetap jaga pola makan bergizi seimbang, cukup protein hewani, "
            "dan lakukan pemantauan rutin di Posyandu."
        )
    elif status == "Stunted":
        warna = "#F2A5C4"
        saran = (
            f"Risiko Stunting: {risiko:.1f}% (Stunted)\n"
            f"Nilai Z-Score: {z}\n\n"
            "Anak termasuk pendek (stunted). Menurut PMK No. 2 Tahun 2020, "
            "Z-score antara -3 SD hingga -2 SD menunjukkan gangguan pertumbuhan kronis. "
            "Perlu peningkatan gizi, pemberian PMT, dan konsultasi ke tenaga kesehatan."
        )
    elif status == "Severely Stunted":
        warna = "#F26B6B"
        saran = (
            f"Risiko Stunting: {risiko:.1f}% (Severely Stunted)\n"
            f"Nilai Z-Score: {z}\n\n"
            "Anak tergolong sangat pendek (severely stunted) dengan Z-score < -3 SD. "
            "Sesuai PMK No. 2 Tahun 2020, kondisi ini perlu penanganan segera. "
            "Rujuk ke Puskesmas atau RS untuk evaluasi penyebab dan tata laksana gizi lebih lanjut."
        )
    else:
        warna = "#B0B0B0"
        saran = "Data tidak dapat diinterpretasikan."
    return saran, warna

# ===================== 7️⃣ Endpoint Prediksi =====================
@app.route('/predict_rf', methods=['POST'])
def predict_rf():
    try:
        data = request.get_json()
        umur = float(data.get('umur', 0))
        tinggi = float(data.get('tinggi_badan', 0))
        berat = float(data.get('berat_badan', 0))
        jenis_kelamin = data.get('jenis_kelamin', '').lower()

        if not all([umur, tinggi, berat, jenis_kelamin]):
            return jsonify({'error': 'Data input tidak lengkap!'}), 400

        # --- Hitung Z-Score ---
        z = hitung_zscore(umur, tinggi, jenis_kelamin)
        status_z, risiko, penjelasan = interpretasi_zscore(z)

        # --- Prediksi Model RF ---
        jk_encoded = 1 if 'laki' in jenis_kelamin else 0
        data_baru = pd.DataFrame({
            'Usia_Bulan': [umur],
            'Jenis_Kelamin': [jk_encoded],
            'Tinggi_Badan_cm': [tinggi],
            'Berat_Badan_kg': [berat]
        })
        probas = model.predict_proba(data_baru)[0]
        pred_idx = probas.argmax()
        pred_label = le.inverse_transform([pred_idx])[0]
        prob_rf = probas[pred_idx] * 100

        saran, warna = get_saran_otomatis(status_z, z, risiko)

        return jsonify({
            'status_prediksi': status_z,
            'zscore': z,
            'risiko_persen': round(risiko, 1),
            'kategori_risiko': status_z,  # ✅ biar Blade tahu kategorinya
            'warna_risiko': warna,
            'penjelasan': penjelasan,
            'hasil': saran,
            'model_rf': pred_label,
            'probabilitas_rf': round(prob_rf, 1),
            'dasar_regulasi': (
                "Analisis ini mengacu pada Peraturan Menteri Kesehatan Republik Indonesia "
                "Nomor 2 Tahun 2020 tentang Standar Antropometri Anak, "
                "sebagai dasar penilaian status gizi dan pertumbuhan anak di Indonesia."
            )
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    print("🚀 Flask API SKINKARE aktif (mengacu PMK No. 2 Tahun 2020)")
    port = int(os.environ.get('PORT', 5001))
    app.run(debug=True, host='0.0.0.0', port=port)