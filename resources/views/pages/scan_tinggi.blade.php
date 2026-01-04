@php
    if (Auth::guard('orangtua')->check()) {
        $layout = 'layouts.orangtuanofooter';
    } else {
        $layout = 'layouts.app_nakesnofooter';
    }
@endphp

@extends($layout)
@section('content')
<style>
  #video, #previewImage {
    object-fit: cover;
    transform: rotate(0deg);
  }
  
  .check-item {
    transition: all 0.3s ease;
  }
  
  @keyframes pulse-ring {
    0% { transform: scale(0.95); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.7; }
    100% { transform: scale(0.95); opacity: 1; }
  }
  
  .countdown-animation {
    animation: pulse-ring 1s ease-in-out infinite;
  }

  .body-silhouette {
    position: relative;
    width: 55%;
    height: 88%;
  }
  
  .body-silhouette::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    height: 100%;
    border: 4px dashed rgba(255, 255, 255, 0.8);
    border-radius: 45% 45% 5% 5%;
    transition: all 0.3s ease;
  }

  .btn-pink {
    background-color: #E9B9C5;
    color: white;
  }
  
  .btn-pink:hover {
    background-color: #d9a3b1;
  }

  .btn-pink-light {
    background-color: #f5d4dd;
    color: white;
  }
  
  .btn-pink-light:hover {
    background-color: #E9B9C5;
  }
</style>

<div class="min-h-screen bg-white flex flex-col items-center justify-between p-4 pt-20">

  <div class="flex-1 flex flex-col items-center justify-center w-full max-w-2xl mt-4">
    <div class="relative w-full bg-gray-200 rounded-2xl overflow-hidden shadow-lg aspect-[3/4]">
      <video id="video" autoplay playsinline muted class="w-full h-full object-cover bg-black hidden rounded-2xl"></video>
      <img id="previewImage" class="w-full h-full object-cover hidden rounded-2xl" />
      <canvas id="canvas" class="hidden"></canvas>
      
      <!-- Siluet hanya muncul saat kamera aktif -->
      <div id="siluetContainer" class="absolute inset-0 flex items-end justify-center pb-4 pointer-events-none hidden">
        <div id="siluetBox" class="body-silhouette"></div>
      </div>

      <div id="countdownOverlay" class="absolute inset-0 bg-black/50 items-center justify-center hidden z-20">
        <div class="text-white text-center">
          <div id="countdownNumber" class="text-9xl font-bold countdown-animation">3</div>
          <p class="text-2xl mt-4">Bersiap untuk foto...</p>
        </div>
      </div>

      <div id="aiHint" class="absolute top-3 left-1/2 -translate-x-1/2 bg-black/80 text-white text-base px-5 py-2 rounded-full shadow-lg max-w-[90%] text-center z-10 hidden">
        📸 Posisikan anak di dalam siluet
      </div>

      <div id="checklistBox" class="absolute bottom-4 left-4 bg-black/80 text-white text-sm rounded-xl px-4 py-3 space-y-2 shadow-xl z-10 min-w-[220px] hidden">
        <div class="font-bold text-base mb-2 border-b border-gray-500 pb-2">📋 Status Pemindaian</div>
        <div id="checkCahaya" class="flex items-center gap-2">
          <span class="text-xl">⏳</span>
          <span class="text-yellow-300">Memeriksa cahaya...</span>
        </div>
        <div id="checkPosisi" class="flex items-center gap-2">
          <span class="text-xl">⏳</span>
          <span class="text-yellow-300">Memeriksa posisi...</span>
        </div>
        <div id="checkJarak" class="flex items-center gap-2">
          <span class="text-xl">⏳</span>
          <span class="text-yellow-300">Memeriksa jarak...</span>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap justify-center gap-3 mt-5">
      <button id="btnStart" class="btn-pink px-5 py-2.5 rounded-full shadow-md font-semibold">
        Aktifkan Kamera
      </button>
      <button id="btnStop" class="btn-pink px-5 py-2.5 rounded-full shadow-md font-semibold hidden">
        Matikan Kamera
      </button>

      <label class="btn-pink px-5 py-2.5 rounded-full cursor-pointer shadow-md font-semibold">
        <input type="file" accept="image/*" id="fileInput" class="hidden">
        Ambil / Pilih Gambar
      </label>
    </div>

    <div id="hasilBox" class="w-full bg-green-100 rounded-t-3xl py-6 px-6 text-center shadow-inner mt-6 hidden">
      <h2 id="hasilTinggiBox" class="text-2xl font-bold text-gray-700 mb-3"></h2> 
      <div class="flex flex-col sm:flex-row justify-center gap-3"> 
        <button id="btnNext" onclick="openPopup()" class="btn-pink px-6 py-2 rounded-full font-semibold"> 
          Lanjut 
        </button> 
        <button onclick="window.history.back()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-full font-semibold"> 
          Kembali 
        </button> 
      </div> 
    </div>
  </div>

  <div id="popup" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-lg p-6 w-80 text-center" id="popupContent">

      <div id="modeHasil">
        <p class="text-gray-800 mb-4">Hasil Scan Tinggi anak anda adalah:</p>
        <div class="relative mb-4">
          <input
            id="popupInput"
            type="text"
            readonly
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center font-bold text-xl pr-12"
          >
          <span
            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold text-lg"
          >
            cm
          </span>
        </div>
          
        <p class="text-gray-700 mb-4">Apakah anda ingin melakukan Input manual?</p>

        <div class="flex justify-center gap-3">
          <button onclick="simpanDanLanjut()" class="btn-pink px-4 py-2 rounded-full font-semibold">
            Input Berat Badan
          </button>

          <button onclick="switchToManual()" class="btn-pink-light px-4 py-2 rounded-full font-semibold">
            Input Manual
          </button>
        </div>

        <button onclick="closePopup()" class="mt-4 text-sm text-gray-500 hover:text-gray-700 underline">Tutup</button>
      </div>

      <div id="modeManual" class="hidden">
        <p class="text-gray-800 mb-4">Masukkan tinggi anak:</p>

        <input id="manualInput" type="number" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 text-center" placeholder="contoh: 90">

        <div class="flex justify-center gap-3">
          <button onclick="saveManual()" class="btn-pink px-4 py-2 rounded-full font-semibold">
            Simpan
          </button>

          <button onclick="switchToHasil()" class="btn-pink-light px-4 py-2 rounded-full font-semibold">
            Kembali
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const video = document.getElementById("video");
const canvas = document.getElementById("canvas");
const previewImage = document.getElementById("previewImage");
const btnStart = document.getElementById("btnStart");
const btnStop = document.getElementById("btnStop");
const fileInput = document.getElementById("fileInput");
const hasilBox = document.getElementById("hasilBox");
const hasilTinggiBox = document.getElementById("hasilTinggiBox");
const popupInput = document.getElementById("popupInput");
const siluetBox = document.getElementById("siluetBox");
const siluetContainer = document.getElementById("siluetContainer");
const countdownOverlay = document.getElementById("countdownOverlay");
const countdownNumber = document.getElementById("countdownNumber");
const aiHint = document.getElementById("aiHint");
const checklistBox = document.getElementById("checklistBox");
const checkCahaya = document.getElementById("checkCahaya");
const checkPosisi = document.getElementById("checkPosisi");
const checkJarak = document.getElementById("checkJarak");

// ⚙️ CONFIG - Ganti URL ini sesuai server AI Anda
const AI_SERVER_URL = "{{ env('FLASK_APP_URL') }}";

let stream = null;
let tinggiTerakhir = null;
let precheckInterval = null;
let countdownTimer = null;
let countdownActive = false;
let allOkCounter = 0;
let cameraActive = false;

const loadingAnim = `
<div class="flex justify-center gap-2 text-gray-800 font-semibold">
  <div class="w-6 h-6 border-4 border-green-600 border-t-transparent rounded-full animate-spin"></div>
  Menghitung tinggi anak...
</div>`;

/* ========== KAMERA ========== */
btnStart.onclick = async () => {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ 
      video: { 
        facingMode: "environment",
        width: { ideal: 1280 },
        height: { ideal: 1920 }
      }
    });
    video.srcObject = stream;
    video.classList.remove("hidden");
    previewImage.classList.add("hidden");
    btnStart.classList.add("hidden");
    btnStop.classList.remove("hidden");
    
    // Tampilkan siluet dan checklist saat kamera aktif
    siluetContainer.classList.remove("hidden");
    aiHint.classList.remove("hidden");
    checklistBox.classList.remove("hidden");
    
    cameraActive = true;
    
    video.onloadedmetadata = () => {
      console.log("✅ Video ready, starting precheck...");
      startPrecheckAI();
    };
    
  } catch(err) {
    alert("Tidak bisa mengakses kamera: " + err.message);
  }
};

btnStop.onclick = () => {
  if (stream) {
    stream.getTracks().forEach(t => t.stop());
  }

  if (precheckInterval) {
    clearInterval(precheckInterval);
    precheckInterval = null;
  }

  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }

  countdownActive = false;
  allOkCounter = 0;
  cameraActive = false;
  
  video.classList.add("hidden");
  btnStop.classList.add("hidden");
  btnStart.classList.remove("hidden");
  countdownOverlay.classList.add("hidden");
  
  // Sembunyikan siluet dan checklist
  siluetContainer.classList.add("hidden");
  aiHint.classList.add("hidden");
  checklistBox.classList.add("hidden");
  
  resetSiluetColor();
};

/* ========== UPLOAD FILE - LANGSUNG PREDICT TANPA PRECHECK ========== */
fileInput.onchange = () => {
  const file = fileInput.files[0];
  if(!file) return;
  
  // Tampilkan preview
  previewImage.src = URL.createObjectURL(file);
  previewImage.classList.remove("hidden");
  video.classList.add("hidden");
  
  // Sembunyikan siluet saat upload
  siluetContainer.classList.add("hidden");
  aiHint.classList.add("hidden");
  checklistBox.classList.add("hidden");
  
  // Langsung predict tanpa precheck
  hasilBox.classList.remove("hidden");
  hasilTinggiBox.innerHTML = loadingAnim;
  processImage(file);
};

/* ========== PRECHECK AI REAL-TIME (UNTUK KAMERA) ========== */
function startPrecheckAI() {
  if (precheckInterval) {
    clearInterval(precheckInterval);
  }

  console.log("🚀 Starting precheck AI...");
  
  // Test koneksi server dulu
  fetch(`${AI_SERVER_URL}/`)
    .then(r => r.json())
    .then(data => {
      console.log("✅ Server connected:", data);
    })
    .catch(err => {
      console.error("⚠️ Server not reachable:", err);
      updateCheckItem(checkCahaya, 'error', 'Server AI tidak terhubung');
      updateCheckItem(checkPosisi, 'error', 'Server AI tidak terhubung');
      updateCheckItem(checkJarak, 'error', 'Server AI tidak terhubung');
      return;
    });

  precheckInterval = setInterval(() => {
    if (!cameraActive || !video.srcObject || video.videoWidth === 0) {
      console.log("⏸ Precheck paused: camera not ready");
      return;
    }

    if (countdownActive) {
      console.log("⏸ Precheck paused: countdown active");
      return;
    }

    console.log("📸 Running precheck...");

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(video, 0, 0);

    canvas.toBlob(blob => {
      if (!blob) {
        console.error("❌ Failed to create blob");
        return;
      }

      let fd = new FormData();
      fd.append("file", blob);

      fetch(`${AI_SERVER_URL}/precheck`, {
        method: "POST",
        body: fd
      })
      .then(r => {
        if (!r.ok) {
          throw new Error(`HTTP ${r.status} - Server AI tidak merespon. Pastikan Flask berjalan di ${AI_SERVER_URL}`);
        }
        return r.json();
      })
      .then(res => {
        console.log("✅ Precheck result:", res);
        updateChecklistUI(res);

        // Cek apakah semua kriteria OK
        if (res.cahaya_ok && res.posisi_ok && res.jarak_ok) {
          allOkCounter++;
          console.log(`✅ All OK counter: ${allOkCounter}`);
          
          setSiluetColor('green');
          
          if (allOkCounter >= 2 && !countdownActive) {
            console.log("🎯 Starting countdown!");
            startCountdown();
          }
        } else {
          allOkCounter = 0;
          resetSiluetColor();
        }
      })
      .catch(err => {
        console.error("❌ Precheck error:", err);
        updateCheckItem(checkCahaya, 'error', 'Koneksi terputus');
        updateCheckItem(checkPosisi, 'error', 'Koneksi terputus');
        updateCheckItem(checkJarak, 'error', 'Koneksi terputus');
      });
    }, "image/jpeg", 0.8);
  }, 1500);
}

/* ========== UPDATE CHECKLIST UI (3 KRITERIA TERPISAH) ========== */
function updateCheckItem(element, status, message) {
  const iconSpan = element.querySelector('span:first-child');
  const textSpan = element.querySelector('span:last-child');
  
  if (status === 'ok') {
    iconSpan.textContent = '✅';
    textSpan.textContent = message;
    textSpan.className = 'text-green-300';
  } else if (status === 'error') {
    iconSpan.textContent = '❌';
    textSpan.textContent = message;
    textSpan.className = 'text-red-300';
  } else {
    iconSpan.textContent = '⏳';
    textSpan.textContent = message;
    textSpan.className = 'text-yellow-300';
  }
}

function updateChecklistUI(res) {
  // Update Cahaya
  if (res.cahaya_ok) {
    updateCheckItem(checkCahaya, 'ok', 'Cahaya cukup');
  } else {
    updateCheckItem(checkCahaya, 'error', res.cahaya_message || 'Cahaya belum sesuai');
  }
  
  // Update Posisi
  if (res.posisi_ok) {
    updateCheckItem(checkPosisi, 'ok', 'Posisi sudah tepat');
  } else {
    updateCheckItem(checkPosisi, 'error', res.posisi_message || 'Posisi belum sesuai');
  }
  
  // Update Jarak
  if (res.jarak_ok) {
    updateCheckItem(checkJarak, 'ok', 'Jarak sudah sesuai');
  } else {
    updateCheckItem(checkJarak, 'error', res.jarak_message || 'Jarak belum sesuai');
  }
}

/* ========== SILUET COLOR HELPERS ========== */
function setSiluetColor(color) {
  const style = document.getElementById('siluet-style') || document.createElement('style');
  style.id = 'siluet-style';
  
  if (color === 'green') {
    style.innerHTML = `.body-silhouette::before { border-color: rgba(34, 197, 94, 0.9) !important; }`;
  } else if (color === 'red') {
    style.innerHTML = `.body-silhouette::before { border-color: rgba(239, 68, 68, 0.9) !important; }`;
  } else {
    style.innerHTML = `.body-silhouette::before { border-color: rgba(255, 255, 255, 0.8) !important; }`;
  }
  
  if (!document.getElementById('siluet-style')) {
    document.head.appendChild(style);
  }
}

function resetSiluetColor() {
  setSiluetColor('white');
}

/* ========== COUNTDOWN SEBELUM AUTO-CAPTURE ========== */
function startCountdown() {
  countdownActive = true;
  countdownOverlay.classList.remove("hidden");
  countdownOverlay.classList.add("flex");
  
  let count = 3;
  countdownNumber.textContent = count;

  countdownTimer = setInterval(() => {
    count--;
    
    if (count > 0) {
      countdownNumber.textContent = count;
    } else {
      clearInterval(countdownTimer);
      countdownTimer = null;
      
      autoCapture();
      
      countdownOverlay.classList.add("hidden");
      countdownOverlay.classList.remove("flex");
      countdownActive = false;
      allOkCounter = 0;
      resetSiluetColor();
    }
  }, 1000);
}

/* ========== AUTO CAPTURE ========== */
function autoCapture() {
  hasilBox.classList.remove("hidden");
  hasilTinggiBox.innerHTML = loadingAnim;
  
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext("2d").drawImage(video, 0, 0);
  
  canvas.toBlob(blob => {
    const url = URL.createObjectURL(blob);
    previewImage.src = url;
    previewImage.classList.remove("hidden");
    
    video.classList.add("hidden");
    
    // Sembunyikan siluet setelah capture
    siluetContainer.classList.add("hidden");
    aiHint.classList.add("hidden");
    checklistBox.classList.add("hidden");
    
    if (precheckInterval) {
      clearInterval(precheckInterval);
      precheckInterval = null;
    }
    
    processImage(blob);
  }, "image/png");
}

/* ========== PROSES AI (HITUNG TINGGI) ========== */
function processImage(fileImg) {
  let formData = new FormData();
  formData.append("file", fileImg);

  fetch(`${AI_SERVER_URL}/predict`, {
    method: "POST",
    body: formData
  })
  .then(r => {
    if (!r.ok) {
      throw new Error(`HTTP ${r.status} - Server AI tidak merespon`);
    }
    return r.json();
  })
  .then(result => {
    console.log("Hasil AI:", result);
    
    if (!result.tinggi || isNaN(result.tinggi) || result.tinggi <= 0) {
      hasilTinggiBox.innerHTML = `<span class="text-red-600 font-semibold">⚠ Gagal mendeteksi tinggi. Silakan coba lagi dengan posisi lebih baik.</span>`;
      return;
    }

    const tinggi = Math.round(result.tinggi);
    tinggiTerakhir = tinggi;

    hasilTinggiBox.innerHTML = `
      <span class="text-3xl font-bold text-green-700 animate-pulse">
        Tinggi Anak: ${tinggi} cm
      </span>`;

    popupInput.value = tinggi;
  })
  .catch(err => {
    console.error("Error processing:", err);
    hasilTinggiBox.innerHTML = `<span class="text-red-600 font-semibold">⚠ Server AI tidak terhubung. Pastikan Flask berjalan di ${AI_SERVER_URL}</span>`;
  });
}

/* ========== POPUP CONTROL ========== */
function openPopup() {
  if (!tinggiTerakhir || tinggiTerakhir === "" || tinggiTerakhir <= 0) {
    alert("Silakan scan atau input tinggi terlebih dahulu!");
    return;
  }

  popupInput.value = tinggiTerakhir;

  const popup = document.getElementById("popup");
  popup.classList.remove("hidden");
  popup.classList.add("flex");
  switchToHasil();
}

function closePopup() {
  const popup = document.getElementById("popup");
  popup.classList.add("hidden");
  popup.classList.remove("flex");
}

function switchToManual() {
  document.getElementById("modeHasil").classList.add("hidden");
  document.getElementById("modeManual").classList.remove("hidden");
}

function switchToHasil() {
  document.getElementById("modeManual").classList.add("hidden");
  document.getElementById("modeHasil").classList.remove("hidden");
}

/* ========== SIMPAN DARI POPUP (HASIL SCAN) ========== */
function simpanDanLanjut() {
  const tinggi = popupInput.value;
  
  if (!tinggi || tinggi <= 0) {
    alert("Tinggi tidak boleh kosong");
    return;
  }

  fetch("{{ route('scan_tinggi.store', ['id' => request()->route('id')]) }}", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": "{{ csrf_token() }}"
    },
    body: JSON.stringify({ tinggi_badan: tinggi })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = data.redirect_url;
    }
  })
  .catch(err => {
    alert("Gagal menyimpan data");
    console.error(err);
  });
}

/* ========== SIMPAN MANUAL ========== */
function saveManual() {
  const val = document.getElementById("manualInput").value;

  if (!val || val <= 0) {
    alert("Tinggi tidak boleh kosong atau 0");
    return;
  }

  tinggiTerakhir = val;
  popupInput.value = val;

  hasilBox.classList.remove("hidden");
  hasilTinggiBox.innerHTML = `
    <div class="flex flex-col items-center">
      <div class="flex items-center gap-2 mt-2 text-green-700">
        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-3xl font-bold">Tinggi Anak: ${val} cm</span>
      </div>
    </div>`;

  closePopup();
}
</script>

@endsection