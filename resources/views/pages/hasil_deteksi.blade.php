@php
    if (Auth::guard('orangtua')->check()) {
        $layout = 'layouts.app';
    } else {
        $layout = 'layouts.app_nakes';
    }
@endphp

@extends($layout)

@section('content')
<div class="min-h-[calc(100vh-160px)] bg-white px-4 py-6">

    <div class="bg-white shadow-xl rounded-3xl p-10 w-full max-w-3xl border border-pink-100 mx-auto">
        <!-- Judul -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-semibold text-pink-500">Hasil Deteksi Kesehatan Anak</h1>
            <p class="text-gray-500 mt-2">Analisis berdasarkan Standar Antropometri Anak (PMK No. 2 Tahun 2020)</p>
        </div>

        <!-- Data Anak -->
        <div class="bg-pink-100/50 rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-700 text-center mb-3 border-b border-pink-200 pb-1">Data Anak</h2>
            <div class="grid grid-cols-2 gap-y-2 text-gray-700 text-sm">
                <p><strong>Nama</strong></p><p>: {{ $anak->nama_lengkap }}</p>
                <p><strong>Jenis Kelamin</strong></p><p>: {{ $anak->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}</p>
                <p><strong>Umur</strong></p><p>: {{ $anak->umur }} bulan</p>
                <p><strong>Tinggi Badan</strong></p><p>: {{ number_format($pemeriksaan->tinggi_badan, 1) }} cm</p>
                <p><strong>Berat Badan</strong></p><p>: {{ number_format($pemeriksaan->berat_badan, 1) }} kg</p>
            </div>
        </div>

        <!-- Hasil Analisis -->
        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold text-[#E573A8] mb-2">Hasil Analisis</h3>
            <div class="bg-gradient-to-r from-[#D8F3F1] to-[#FFE5EE] rounded-2xl shadow p-6">
                <p class="text-gray-700 text-sm mb-2">
                    Status: <span class="font-semibold">{{ $hasil->status_prediksi ?? 'Tidak diketahui' }}</span>
                </p>
                <p class="text-gray-700 text-sm mb-2">
                    Z-Score: <span class="font-semibold">{{ $hasil->zscore ?? '-' }}</span>
                </p>
                <p class="text-gray-700 text-sm mb-2">
                    Risiko Stunting:
                    <span class="font-bold text-lg" style="color: {{ $hasil->warna_risiko ?? '#808080' }}">
                        {{ $hasil->risiko_persen ?? 0 }}%
                    </span>
                    <span class="text-sm text-gray-600">
                        ({{ $hasil->kategori_risiko ?? 'Tidak diketahui' }})
                    </span>
                </p>
                <p class="text-gray-700 text-sm">
                    Prediksi AI (Random Forest): 
                    <strong>
                    {{ $data['model_rf'] ?? '-' }}</strong> 
({{ $data['probabilitas_rf'] ?? 0 }}%)
                </p>
            </div>
        </div>

        <!-- Interpretasi -->
        <div class="bg-gradient-to-r from-[#FFE6EE] to-[#D8F3F1] rounded-2xl shadow-md p-5 mb-8">
            <h4 class="font-semibold text-gray-700 text-base mb-2">Interpretasi & Rekomendasi:</h4>
            <div class="bg-white rounded-xl p-4 text-sm text-gray-700 leading-relaxed">
                {!! nl2br(e($hasil->hasil ?? 'Belum ada hasil interpretasi.')) !!}
            </div>
        </div>

        <!-- Rekomendasi Makanan (tambahan edukatif) -->
        <div class="bg-[#FFF8E7] rounded-2xl shadow p-5 mb-8">
            <h4 class="font-semibold text-gray-700 text-base mb-2">Rekomendasi Asupan Gizi Seimbang:</h4>
            <ul class="list-disc list-inside text-sm text-gray-700 leading-relaxed">
                <li>Berikan sumber protein hewani setiap hari (telur, ikan, daging ayam, atau hati ayam).</li>
                <li>Tambahkan sayur dan buah berwarna-warni untuk melengkapi vitamin dan mineral.</li>
                <li>Pastikan anak cukup minum air putih dan istirahat yang cukup.</li>
                <li>Rutin datang ke Posyandu atau Puskesmas untuk pemantauan pertumbuhan.</li>
            </ul>
        </div>

        <!-- Grafik -->
        <div class="bg-[#B9E9DD]/20 shadow-xl rounded-3xl p-8 mt-10 w-full max-w-3xl border border-pink-100">
            <h3 class="text-xl font-semibold text-pink-500 text-center mb-4">
                Grafik Tinggi Badan / Umur (Z-Score)
            </h3>
            <div class="w-full" style="height: 350px;">
                <canvas id="chartTbu"></canvas>
            </div>
        </div>

        <!-- Dasar Regulasi -->
        <div class="bg-white rounded-2xl p-5 mt-8 shadow border border-pink-100">
            <h4 class="font-semibold text-gray-700 mb-2">Dasar Regulasi:</h4>
            <p class="text-sm text-gray-700 leading-relaxed">
                {{ $hasil->dasar_regulasi ?? 'Analisis ini mengacu pada Peraturan Menteri Kesehatan Republik Indonesia Nomor 2 Tahun 2020 tentang Standar Antropometri Anak.' }}
            </p>
        </div>

        <!-- Tombol -->
        <div class="flex flex-col sm:flex-row mt-10 gap-4 w-full justify-center">
            <a href="{{ route('barcode.show', $anak->id) }}"
               class="bg-[#ddb9e9] hover:bg-[#6bc6bf] text-white font-semibold p-3 rounded-full shadow text-center transition">
                Download QR Code
            </a>            
            <a href="{{ route('scan_tinggi', $anak->id) }}"
               class="bg-[#53AFA2] hover:bg-[#6bc6bf] text-white font-semibold p-3 rounded-full shadow text-center transition">
                Perbarui Data
            </a>
            <a href="{{ route('halaman_orangtua') }}"
               class="bg-[#E9B9C5] hover:bg-[#d97298] text-white font-semibold p-3 rounded-full shadow text-center transition">
               Kembali
            </a>
        </div>
    </div>
</div>

<!-- CDN Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartTbu').getContext('2d');
const umurArr = [0, 6, 12, 24, 36, 48, 60];
const sdMinus3_vals = [45, 60, 71, 82, 89, 95, 100];
const sdMinus2_vals = [47, 63, 75, 86, 94, 100, 105];
const sdMinus1_vals = [49, 66, 78, 89, 97, 103, 108];
const sd0_vals      = [51, 68, 80, 92, 100, 106, 112];
const sdPlus1_vals  = [53, 70, 83, 95, 104, 110, 116];
const sdPlus2_vals  = [55, 72, 86, 98, 107, 113, 119];
const sdPlus3_vals  = [57, 75, 89, 101, 110, 116, 122];
const pair = (xArr, yArr) => xArr.map((x,i)=>({x,y:yArr[i]}));
const umurAnak = {{ $anak->umur }};
const tinggiAnak = {{ $pemeriksaan->tinggi_badan }};

new Chart(ctx, {
    type: 'line',
    data: {
        datasets: [
            { label: '-3 SD', data: pair(umurArr, sdMinus3_vals), borderColor: '#d2969b', borderWidth: 3, pointRadius: 0, tension: 0.3 },
            { label: '-2 SD', data: pair(umurArr, sdMinus2_vals), borderColor: '#d2b196', borderWidth: 3, pointRadius: 0, tension: 0.3 },
            { label: 'Median', data: pair(umurArr, sd0_vals), borderColor: '#61aa52', borderWidth: 3, pointRadius: 0, tension: 0.3 },
            { label: '+2 SD', data: pair(umurArr, sdPlus2_vals), borderColor: '#9f96d2', borderWidth: 3, pointRadius: 0, tension: 0.3 },
            {
                label: 'Anak',
                data: [{ x: umurAnak, y: tinggiAnak }],
                pointStyle: 'rectRot',
                pointBackgroundColor: 'black',
                pointBorderColor: 'black',
                pointRadius: 6,
                showLine: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true } }
        },
        scales: {
            x: { type: 'linear', title: { display: true, text: 'Umur (bulan)' }, min: 0, max: 60 },
            y: { title: { display: true, text: 'Tinggi Badan (cm)' }, min: 40, max: 130 }
        }
    }
});
</script>
@endsection
