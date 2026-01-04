<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Anak;
use App\Models\Pemeriksaan;
use App\Models\HasilDeteksi;

class ScanController extends Controller
{
    // ===================== PREDICT TINGGI (CNN) =====================
    public function predict(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        try {
            $file = $request->file('foto');

            $response = Http::attach(
                'file',
                file_get_contents($file),
                $file->getClientOriginalName()
            )->timeout(30)->post(env('FLASK_APP_URL') . '/predict');
            if (!$response->ok()) {
                Log::error("Flask predict error:", [$response->body()]);
                return response()->json(['error' => 'Gagal memproses gambar'], 500);
            }

            $data = $response->json();
            return response()->json([
                'tinggi' => $data['tinggi'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error("Predict Error:", [$e->getMessage()]);
            return response()->json(['error' => 'Tidak dapat terhubung ke server AI'], 500);
        }
    }

    // ===================== SIMPAN HASIL TINGGI =====================
    public function storeTinggi(Request $request, $id)
    {
        $request->validate([
            'tinggi_badan' => 'required|numeric',
        ]);

        try {
            $anak = Anak::findOrFail($id);

            $pemeriksaan = Pemeriksaan::create([
                'id' => $anak->id,
                'tanggal_pemeriksaan' => now(),
                'tinggi_badan' => $request->tinggi_badan,
                'berat_badan' => null,
                'metode_input' => $request->metode_input ?? 'otomatis',
            ]);

            Log::info('Tinggi tersimpan', $pemeriksaan->toArray());

            return response()->json([
                'success' => true,
                'redirect_url' => route('scan.berat', $pemeriksaan->id_pemeriksaan)
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal simpan tinggi: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal menyimpan tinggi'], 500);
        }
    }

    // ===================== INPUT BERAT =====================
    public function berat($id_pemeriksaan)
    {
        $pemeriksaan = Pemeriksaan::findOrFail($id_pemeriksaan);
        $anak = $pemeriksaan->anak; // relasi ke tabel anaks

        return view('pages.input_berat', compact('anak', 'pemeriksaan'));
    }

    // ===================== SIMPAN BERAT =====================
    public function storeBerat(Request $request, $id_pemeriksaan)
    {
        $request->validate([
            'berat_badan' => 'required|numeric|min:1|max:100',
        ]);

        try {
            $pemeriksaan = Pemeriksaan::findOrFail($id_pemeriksaan);

            $pemeriksaan->update([
                'berat_badan' => $request->berat_badan,
            ]);

            Log::info('Berat tersimpan', [
                'pemeriksaan_id' => $id_pemeriksaan,
                'berat' => $request->berat_badan
            ]);
        return redirect()->route('scan.hasil', ['id' => $id_pemeriksaan])
                        ->with('success', 'Data berat berhasil disimpan!');

        } catch (\Exception $e) {
            Log::error('Gagal simpan berat: ' . $e->getMessage());
            return back()->with('error', 'Gagal menyimpan berat badan');
        }
    }

// ===================== HASIL DETEKSI (Random Forest) =====================
public function hasil($id)
{
    $pemeriksaan = Pemeriksaan::findOrFail($id);
    $anak = $pemeriksaan->anak; // relasi ke tabel anaks

    try {
        // Kirim data ke Flask
        $response = Http::timeout(60)->post(env('FLASK_RF_URL') . '/predict_rf', [
            'umur' => (float) $anak->umur,
            'tinggi_badan' => (float) $pemeriksaan->tinggi_badan,
            'berat_badan' => (float) $pemeriksaan->berat_badan,
            'jenis_kelamin' => $anak->jenis_kelamin === 'L' ? 'laki-laki' : 'perempuan',
        ]);

        if ($response->failed()) {
            Log::error('Flask RF error:', [$response->body()]);
            return back()->with('error', 'Gagal memproses data di server AI.');
        }

        $data = $response->json();

        // Simpan ke tabel hasil_deteksi
        $hasil = \App\Models\HasilDeteksi::create([
            'id_pemeriksaan' => $pemeriksaan->id_pemeriksaan,
            'id' => $anak->id,
            'status_prediksi' => $data['status_prediksi'] ?? 'Tidak diketahui',
            'zscore' => $data['zscore'] ?? null,
            'risiko_persen' => $data['risiko_persen'] ?? null,
            'kategori_risiko' => $data['kategori_risiko'] ?? null,
            'warna_risiko' => $data['warna_risiko'] ?? '#808080',
            'hasil' => $data['hasil'] ?? 'Tidak diketahui',
        ]);

        Log::info('Hasil deteksi tersimpan', $hasil->toArray());

        // Tampilkan di view hasil_deteksi.blade.php
        return view('pages.hasil_deteksi', compact(
            'anak',
            'pemeriksaan',
            'hasil',
            'data'
        ));

    } catch (\Exception $e) {
        Log::error('Gagal terhubung ke Flask RF: ' . $e->getMessage());
        return back()->with('error', 'Tidak dapat terhubung ke server AI.');
    }
}
}
