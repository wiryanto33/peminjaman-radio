<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Penyerahan Barang</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.4;
        }

        .container {
            padding: 20px 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            margin: 0 0 6px 0;
            font-size: 20px;
            letter-spacing: 0.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sub {
            font-size: 10px;
            color: #666;
            font-style: italic;
        }

        /* Meta Info */
        .meta {
            margin: 16px 0 20px;
            background: #f8f8f8;
            padding: 10px 12px;
            border-radius: 4px;
        }

        .meta table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 3px 8px;
            vertical-align: top;
        }

        .meta td:first-child,
        .meta td:nth-child(3) {
            width: 120px;
            font-weight: 600;
        }

        .meta td:nth-child(2),
        .meta td:nth-child(4) {
            width: calc(50% - 120px);
        }

        /* Section Title */
        .section-title {
            font-weight: bold;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            font-size: 12px;
            color: #333;
            padding-bottom: 4px;
            border-bottom: 2px solid #333;
        }

        /* Main Table */
        table.main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.main-table th,
        table.main-table td {
            border: 1px solid #444;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }

        table.main-table th {
            background: #e0e0e0;
            font-weight: 600;
            width: 30%;
        }

        table.main-table td {
            background: #fff;
        }

        table.main-table .section-header {
            background: #333;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table.main-table .photo-cell {
            text-align: center;
            padding: 10px;
            background: #fafafa;
        }

        /* Photo */
        .photo {
            max-width: 100%;
            max-height: 100px;
            object-fit: contain;
            border: 1px solid #ccc;
            padding: 8px;
            border-radius: 4px;
            background: #fff;
        }

        /* Signature Section */
        .sign {
            margin-top: 30px;
            display: table;
            width: 100%;
            table-layout: fixed;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .sign .box {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .sign .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 50px;
            font-weight: 600;
        }

        .sign .name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
            text-decoration: underline;
        }

        .sign .detail {
            font-size: 10px;
            color: #555;
            line-height: 1.5;
        }

        /* Note */
        .note {
            margin-top: 20px;
            padding: 10px 12px;
            background: #fff9e6;
            border-left: 3px solid #f0ad4e;
            font-size: 10px;
            color: #555;
            line-height: 1.5;
        }

        .note strong {
            color: #333;
        }
    </style>
    @php
        use Illuminate\Support\Facades\Storage;
        $radio = $peminjaman->radio;
        $peminjam = $peminjaman->peminjam;
        $petugas = $peminjaman->petugas;
        $imagePath =
            $radio && $radio->image && Storage::disk('public')->exists($radio->image)
                ? Storage::disk('public')->path($radio->image)
                : null;
    @endphp
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Bukti Penyerahan Barang</h1>
            <div class="sub">Dokumen ini dicetak pada: {{ $generatedAt->format('d/m/Y H:i') }} WIB</div>
        </div>

        <!-- Meta Information -->
        <div class="meta">
            <table>
                <tr>
                    <td><strong>Kode Peminjaman</strong></td>
                    <td>: {{ $peminjaman->kode_peminjaman }}</td>
                    <td><strong>Status</strong></td>
                    <td>: <span style="text-transform: capitalize;">{{ $peminjaman->status }}</span></td>
                </tr>
                <tr>
                    <td><strong>Tanggal Pinjam</strong></td>
                    <td>: {{ optional($peminjaman->tgl_pinjam)->format('d/m/Y H:i') }} WIB</td>
                    <td><strong>Jatuh Tempo</strong></td>
                    <td>: {{ optional($peminjaman->tgl_jatuh_tempo)->format('d/m/Y H:i') }} WIB</td>
                </tr>
            </table>
        </div>

        <!-- Section Title -->
        <div class="section-title">Detail Peminjaman</div>

        <!-- Main Combined Table -->
        <table class="main-table">
            <!-- Data Peminjam Section -->
            <tr>
                <td colspan="2" class="section-header">Data Peminjam</td>
            </tr>
            <tr>
                <th>Nama</th>
                <td>{{ $peminjam->name ?? '-' }}</td>
            </tr>
            <tr>
                <th>Pangkat</th>
                <td>{{ $peminjam->pangkat ?? '-' }}</td>
            </tr>
            <tr>
                <th>Korps</th>
                <td>{{ $peminjam->korps ?? '-' }}</td>
            </tr>
            <tr>
                <th>NRP</th>
                <td>{{ $peminjam->nrp ?? '-' }}</td>
            </tr>
            <tr>
                <th>Satuan</th>
                <td>{{ $peminjam->satuan ?? '-' }}</td>
            </tr>

            <!-- Data Barang Section -->
            <tr>
                <td colspan="2" class="section-header">Data Barang</td>
            </tr>
            <tr>
                <th>Radio</th>
                <td>{{ trim(($radio->merk ?? '') . ' ' . ($radio->model ?? '')) ?: '-' }}</td>
            </tr>
            <tr>
                <th>Serial Number</th>
                <td>{{ $radio->serial_no ?? '-' }}</td>
            </tr>
            <tr>
                <th>Kategori</th>
                <td>{{ $radio->kategori->nama ?? '-' }}</td>
            </tr>
            <tr>
                <th>Jumlah</th>
                <td>{{ (int) ($peminjaman->jumlah ?? 1) }}</td>
            </tr>
            <tr>
                <th>Keperluan</th>
                <td>{{ $peminjaman->keperluan ?? '-' }}</td>
            </tr>
            <tr>
                <th>Lokasi Penggunaan</th>
                <td>{{ $peminjaman->lokasi_penggunaan ?? '-' }}</td>
            </tr>
            <tr>
                <th>Catatan</th>
                <td>{{ $peminjaman->catatan ?? '-' }}</td>
            </tr>

            <!-- Photo Section -->
            @if ($imagePath)
                <tr>
                    <td colspan="2" class="photo-cell">
                        <img class="photo" src="{{ $imagePath }}" alt="Foto Radio">
                    </td>
                </tr>
            @endif
        </table>

        <!-- Signature Section -->
        <div class="sign">
            <div class="box">
                <div class="label">Peminjam</div>
                <div class="name">{{ $peminjam->name ?? '(______________________)' }}</div>
                <div class="detail">
                    @if ($peminjam->pangkat || $peminjam->korps)
                        {{ trim(($peminjam->pangkat ?? '') . ' ' . ($peminjam->korps ?? '') . ' ' . ($peminjam->nrp ?? '')) }}<br>
                    @endif
                </div>
            </div>
            <div class="box">
                <div class="label">Petugas</div>
                <div class="name">{{ $petugas->name ?? '(______________________)' }}</div>
                <div class="detail">
                    @if ($petugas->pangkat || $petugas->korps)
                        {{ trim(($petugas->pangkat ?? '') . ' ' . ($petugas->korps ?? '') . ' ' . ($petugas->nrp ?? '')) }}<br>
                    @endif
                </div>
            </div>
        </div>

        <!-- Note -->
        <div class="note">
            <strong>Catatan Penting:</strong> Barang yang dipinjam menjadi tanggung jawab peminjam dan wajib
            dikembalikan dalam kondisi baik serta tepat waktu sesuai tanggal jatuh tempo. Harap simpan bukti penyerahan
            ini sebagai arsip.
        </div>
    </div>
</body>

</html>
