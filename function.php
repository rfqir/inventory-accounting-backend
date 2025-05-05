<?php

require '../function/function.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set('Asia/Jakarta');
//insert req
if (isset($_POST['tokoinput'])) {
    $jum = $_POST['jum'];
    $sku = $_POST['sku'];
    $inv = $_POST['inv'];
    $status = $_POST['status'];
    $stat = $_POST['stat'];
    $tipe = $_POST['tipe_pesanan'];
    $quantity = $_POST['quantity'];
    $requester = $_POST['requester'];
    $date = date('Y-m-d H:i:s');

    for ($i = 0; $i < $jum; $i++) {
        if ($inv[$i] == '0') {
            $select = mysqli_query($conn, "SELECT id_toko FROM toko_id WHERE sku_toko='$sku[$i]'");
            $data = mysqli_fetch_array($select);
            $idt = $data['id_toko'];

            if ($select) {
                // cek apakah id_toko ini sudah direquest atau belum
                $cekrefill = mysqli_query($conn, "SELECT COUNT(*) AS request_count 
                                                FROM request_id 
                                                WHERE id_toko = '$idt' 
                                                AND (status_req = 'unprocessed' OR  status_req = 'On Process' OR status_req = 'On Process Worker')
                                                AND type_req = 'refill'");
                $request_count_data = mysqli_fetch_assoc($cekrefill);
                $request_count = $request_count_data['request_count'];
                if ($request_count > 0) {
                    echo '
                    
                    <script>
                    
                    alert("Sudah ada percobaan refill untuk sku ini");
                    
                    window.location.href="?url=product";
                    
                    </script>';
                } else {
                    // cekk udah pernah masuk atau belum
                    $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idt $date'");
                    $hitung = mysqli_num_rows($cek);
                    if ($hitung == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, quantity_req, requester, type_req, status_req, status_toko) VALUES('$idt $date','$idt','$quantity[$i]','$requester','$status','$stat', 'on process toko')");
                        $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'On Process' WHERE id_toko = '$idt' AND status = 'unprocessed'");
                        header('location:?url=product');
                    }
                }
            }
        } else {
            $select = mysqli_query($conn, "SELECT id_toko FROM toko_id WHERE sku_toko='$sku[$i]'");
            $data = mysqli_fetch_array($select);
            $idt = $data['id_toko'];

            if ($select) {
                $selectlist = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish='$idt'");
                $list = mysqli_fetch_array($selectlist);

                $id_komp = $list['id_komponen'];
                if ($id_komp > 1999999) {
                    $selectproductgudang = mysqli_query($conn, "SELECT SUM(quantity) AS quantity FROM gudang_id WHERE id_product='$id_komp' GROUP BY id_product");
                    $datagudang = mysqli_fetch_array($selectproductgudang);
                    $quantitytotal = $datagudang['quantity'];

                    if ($quantity[$i] > $quantitytotal) {
                        echo '
                    
                    <script>
                    
                    alert("Quantity yang anda minta melebihi yang ada");
                    
                    window.location.href="?url=product";
                    
                    </script>';
                    } else {
                        // cek
                        $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idt $inv[$i]'");
                        $hitung = mysqli_num_rows($cek);
                        if ($hitung == 0) {
                            $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, requester, type_req, status_req, tipe_pesanan) VALUES('$idt $inv[$i]','$idt','$inv[$i]','$quantity[$i]','$requester','$status','$stat','$tipe[$i]')");
                            header('location:?url=product');
                        }
                    }
                } elseif ($id_komp < 1999999) {
                    $selectproductgudang = mysqli_query($conn, "SELECT SUM(quantity) AS quantity FROM mateng_id WHERE id_product='$id_komp' GROUP BY id_product");
                    $datagudang = mysqli_fetch_array($selectproductgudang);
                    $quantitytotal = $datagudang['quantity'];

                    if ($quantity[$i] > $quantitytotal) {
                        echo '
                    
                    <script>
                    
                    alert("Quantity yang anda minta melebihi yang ada");
                    
                    window.location.href="?url=product";
                    
                    </script>';
                    } else {
                        $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idt $inv[$i]'");
                        $hitung = mysqli_num_rows($cek);
                        if ($hitung == 0) {
                            $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, requester, type_req, status_req, tipe_pesanan) VALUES('$idt $inv[$i]','$idt','$inv[$i]','$quantity[$i]','$requester','$status','$stat','$tipe[$i]')");
                            header('location:?url=product');
                        }
                    }
                }
            } else {
                echo '
                    
                    <script>
                    
                    alert("Tidak ada Komponen Mentah di Gudang, Harap Lapor Gudang");
                    
                    window.location.href="?url=product";
                    
                    </script>';
            }
        }
    }
}

//Approve Refill
if (isset($_POST['approverefill'])) {
    $quantity = $_POST['quantity'];
    $stat = $_POST['stat'];
    $user = $_POST['user'];
    $idr = $_POST['idr'];
    $displayQuantity = $_POST['displayQuantity'];
    $jum = count($idr);
    $berhasil = array();
    $gagal = array();
    $b = 0;
    $g = 0;
    for ($i = 0; $i < $jum; $i++) {
        $currQuantity = (int) $displayQuantity[$i];
        $image = ''; // No file chosen, set an empty image
        if ($currQuantity !== 0) {
            $selectlist = mysqli_query($conn, "SELECT quantity_req, id_toko, type_req FROM request_id WHERE id_request='" . $idr[$i] . "'");
            $datalist = mysqli_fetch_array($selectlist);
            $qtyreq = $datalist['quantity_req'];
            $id_toko = $datalist['id_toko'];
            $type_req = $datalist['type_req'];
            if ($selectlist) {
                $selecttoko = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko='$id_toko'");
                $datatoko = mysqli_fetch_array($selecttoko);
                $quantity_toko = $datatoko['quantity_toko'];
                $tambah = $qtyreq + $quantity_toko;
                if ($qtyreq == $currQuantity) {
                    $select = mysqli_query($conn, "SELECT id_gudang AS idg, jenis_item, quantity_tambah FROM request_total WHERE id_request='" . $idr[$i] . "'");
                    while ($data = mysqli_fetch_array($select)) {
                        $jenis = $data['jenis_item'];
                        $idg = $data['idg'];
                        $quantitytotal = $data['quantity_tambah'];
                        if ($jenis == 'mentah') {
                            $selectgudang = mysqli_query($conn, "SELECT id_gudang, quantity FROM gudang_id WHERE id_gudang='$idg'");
                            $datagudang = mysqli_fetch_array($selectgudang);
                            $quantitygudang = $datagudang['quantity'];
                            $kurang = $quantitygudang - $quantitytotal;
                            if ($selectgudang) {
                                // cek history transaksi
                                $cek = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_gudang WHERE uniq_transaksi = '$idr[$i] $idg'");
                                $hitung = mysqli_num_rows($cek);
                                if ($hitung == 0) {
                                    // history transaksi
                                    $history = mysqli_query($conn, "INSERT INTO transaksi_gudang(uniq_transaksi,stok_sebelum,stok_sesudah,jenis_transaksi,jumlah,id_gudang,id_pengurang) VALUES ('$idr[$i] $idg refill','$quantitygudang','$kurang','$type_req','$quantitytotal','$idg','$idr[$i]') ");
                                    if ($history) {
                                        $update = mysqli_query($conn, "UPDATE gudang_id SET quantity='$kurang' WHERE id_gudang='$idg'");
                                        if ($update) {
                                            $updatetotal = mysqli_query($conn, "UPDATE request_total SET status_total='Approved' WHERE id_request='$idr[$i]'");
                                            if ($updatetotal) {
                                                $updatereq = mysqli_query($conn, "UPDATE request_id SET image_toko='$image', quantity_count='$currQuantity', status_req='Approved', on_duty = '$user' WHERE id_request='$idr[$i]'");
                                                $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'Done' WHERE id_toko = '$id_toko' AND status = 'On Process'");
                                                if ($updatereq) {
                                                    if ($type_req == 'refill') {
                                                        $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$idr[$i] $id_toko refill'");
                                                        $num = mysqli_num_rows($cektransaksi);
                                                        if ($num == 0) {
                                                            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user) VALUES('$idr[$i] $id_toko refill','$quantity_toko','$tambah','refill','$currQuantity','$id_toko','$idr[$i]', '$user')");
                                                            if ($insert) {
                                                                $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$tambah' WHERE id_toko='$id_toko'");
                                                                $berhasil[] = $b++;
                                                            }
                                                        } else {
                                                            echo 'ada data yang sama masuk 2 kali';
                                                        }
                                                    } else {
                                                        echo 'gagal1';
                                                    }
                                                } else {
                                                    echo 'gagal 2';
                                                }
                                            } else {
                                                echo "Gagal mengupdate status total";
                                            }
                                        } else {
                                            echo "Gagal mengupdate quantity gudang";
                                        }
                                    } else {
                                        echo 'gagal3';
                                    }
                                } else {
                                    echo 'gagal 4';
                                }
                            } else {
                                echo "Gagal mendapatkan data gudang";
                            }
                        } elseif ($jenis == 'mateng') {
                            $selectgudang = mysqli_query($conn, "SELECT id_gudang, quantity FROM mateng_id WHERE id_gudang='$idg'");
                            $datagudang = mysqli_fetch_array($selectgudang);
                            $quantitygudang = $datagudang['quantity'];
                            $kurang = $quantitygudang - $quantitytotal;
                            if ($selectgudang) {
                                // cek history transaksi
                                $cek = mysqli_query($conn, "SELECT 	id_transaksi FROM transaksi_gudang WHERE uniq_transaksi = '$idr[$i] $idg'");
                                $hitung = mysqli_num_rows($cek);
                                if ($hitung == 0) {
                                    // history transaksi
                                    $history = mysqli_query($conn, "INSERT INTO transaksi_gudang(uniq_transaksi,stok_sebelum,stok_sesudah,jenis_transaksi,jumlah,id_gudang,id_pengurang) VALUES ('$idr[$i] $idg refill','$quantitygudang','$kurang','$type_req','$quantitytotal','$idg','$idr[$i]') ");
                                    if ($history) {
                                        $update = mysqli_query($conn, "UPDATE mateng_id SET quantity='$kurang' WHERE id_gudang='$idg'");
                                        if ($update) {
                                            $updatetotal = mysqli_query($conn, "UPDATE request_total SET status_total='Approved' WHERE id_request='$idr[$i]'");
                                            if ($updatetotal) {
                                                $updatereq = mysqli_query($conn, "UPDATE request_id SET image_toko='$image', quantity_count='$currQuantity', status_req='Approved', on_duty = '$user' WHERE id_request='$idr[$i]'");
                                                $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'Done' WHERE id_toko = '$id_toko' AND status = 'On Process'");
                                                if ($updatereq) {
                                                    if ($type_req == 'refill') {
                                                        $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$idr[$i] $id_toko'");
                                                        $num = mysqli_num_rows($cektransaksi);
                                                        if ($num == 0) {
                                                            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user) VALUES('$idr[$i] $id_toko refill','$quantity_toko','$tambah','refill','$currQuantity','$id_toko','$idr[$i]', '$user')");
                                                            if ($insert) {
                                                                $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$tambah' WHERE id_toko='$id_toko'");
                                                                $berhasil[] = $b++;
                                                            }
                                                        } else {
                                                            echo 'ada data yang sama masuk 2 kali';
                                                        }
                                                    } else {
                                                    }
                                                }
                                            } else {
                                                echo "Gagal mengupdate status total";
                                            }
                                        } else {
                                            echo "Gagal mengupdate quantity gudang";
                                        }
                                    }
                                }
                            } else {
                                echo "Gagal mendapatkan data gudang";
                            }
                        }
                    }
                } else {
                    $gagal[] = $g++;
                    $update = mysqli_query($conn, "UPDATE request_id SET image_toko='$image', quantity_count='$currQuantity' WHERE id_request='$idr[$i]'");
                    if (!$update) {
                        echo "Gagal mengupdate status request";
                    }
                }
            } else {
            }
        }
    }
    echo '
                    
                    <script>
                    
                    alert(" ' . $b . ' refill berhasil & ' . $g . ' gagal");
                    
                    window.location.href="?url=approve";
                    
                    </script>';
}


if (isset($_POST['approvereadmin'])) {

    $quantityr = $_POST['quantityr'];

    $quantityc = $_POST['quantityc'];

    $stat = $_POST['stat'];

    $idt = $_POST['idt'];

    $idk = $_POST['idk'];

    $idg = $_POST['idg'];



    $jum = count($idt);

    for ($i = 0; $i < $jum; $i++) {

        $update = mysqli_query($conn, "UPDATE request_id SET quantity_req='$quantityr[$i]', quantity_count='$quantityc[$i]' WHERE id_request='$idt[$i]'");

        if ($quantityc[$i] == $quantityr[$i]) {

            $update = mysqli_query($conn, "UPDATE request_id SET quantity_count='$quantity[$i]', status_req='$stat[$i]' WHERE id_request='$idt[$i]'");

            if ($update) {

                $selecttotal = mysqli_query($conn, "SELECT id_total, id_gudang, quantity_tambah FROM request_total WHERE id_request='$idt[$i]'");

                while ($opsi = mysqli_fetch_array($selecttotal)) {

                    $id = $opsi['id_gudang'];

                    $qty = $opsi['quantity_tambah'];

                    $idtol = $opsi['id_total'];



                    if ($selecttotal) {

                        $selectgudang = mysqli_query($conn, "SELECT quantity FROM gudang_id WHERE id_gudang='$id'");

                        $opsi2 = mysqli_fetch_array($selectgudang);

                        $qtyg = $opsi2['quantity'];



                        $kurang = $qtyg - $qty;

                        if ($selectgudang) {

                            $updateg = mysqli_query($conn, "UPDATE gudang_id SET quantity='$kurang' WHERE id_gudang='$id'");

                            if ($updateg) {

                                $updatetol = mysqli_query($conn, "UPDATE request_total SET status_total='$stat[$i]' WHERE id_total='$idtol'");

                                header('location:?url=approve');
                            }
                        } else {
                        }
                    } else {
                    }
                }
            } else {
            }
        } else {
        }

        header('location:?url=approveadmin');
    } {
    }
}



//Edit SKU

if (isset($_POST['addsku'])) {

    $idp = $_POST['idp'];

    $sku = $_POST['sku'];



    $jum = count($idp);

    for ($i = 0; $i < $jum; $i++) {
        $select = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko='$sku[$i]'");
        $data = mysqli_fetch_array($select);
        $skutoko = $data['sku_toko'];

        $hitung = mysqli_num_rows($select);
        if ($skutoko == "-") {
            $edit = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$sku[$i]' WHERE id_product='$idp[$i]'");
            header('location?url=product');
        } else {
            if ($hitung > 0) {
                echo '
                    
                    <script>
                    
                    alert("Data SKU sudah ada");
                    
                    window.location.href="?url=product";
                    
                    </script>';
            } else {
                $edit = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$sku[$i]' WHERE id_product='$idp[$i]'");
                header('location?url=product');
            }
        }
    } {
    }
}

//Edit Toko
if (isset($_POST['edititemsuper'])) {
    $skug = $_POST['skut'];
    $nama = $_POST['nama'];
    $idp = $_POST['idp'];
    $idt = $_POST['idt'];
    $lorong = $_POST['lorong'];
    $toko = $_POST['toko'];
    $maxqty = $_POST['maxqty'];
    $berat = $_POST['berat'];
    $tipe = $_POST['tipe'];
    $per = $_POST['per'];
    $tipe_barang = $_POST['tipe_barang'];

    //gambar

    $allowed_extensions = array('png', 'jpg', 'jpeg', 'svg', 'webp');

    $namaimage = $_FILES['file']['name']; //ambil gambar

    $dot = explode('.', $namaimage);

    $ekstensi = strtolower(end($dot)); //ambil ekstensi

    $ukuran = $_FILES['file']['size']; //ambil size

    $file_tmp = $_FILES['file']['tmp_name']; //lokasi

    //nama acak

    $image = md5(uniqid($namaimage, true) . time()) . '.' . $ekstensi; //compile

    if ($ukuran == 0) {
        $update = mysqli_query($conn, "UPDATE product_toko_id SET nama='$nama' WHERE id_product='$idp'");

        if ($update) {

            $select = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko='$skug'");

            $hitung = mysqli_num_rows($select);
            if ($hitung > 1 && $skug !== '-') {
                echo '

            <script>

                alert("SKU Toko Telah ada");

                window.location.href="?url=product";

            </script>';
            } else {
                $update2 = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$skug', lorong='$lorong', toko='$toko', tipe_barang='$berat', max_qty='$maxqty', tipe = '$tipe', per = '$per', tipe_barang = '$tipe_barang' WHERE id_toko='$idt'");
                header('location:?url=product');
            }
        } else {

            echo '

            <script>

                alert("Barang Tidak bisa di update");

                window.location.href="?url=product";

            </script>';
        }
    } else {

        move_uploaded_file($file_tmp, '../../assets/img/' . $image);

        $update = mysqli_query($conn, "UPDATE product_toko_id SET nama='$nama', image='$image' WHERE id_product='$idp'");

        if ($update) {
            $select = mysqli_query($conn, "SELECT sku_toko, lorong='$lorong', toko='$toko' FROM toko_id WHERE sku_toko='$skug'");
            $hitung = mysqli_num_rows($select);
            if ($hitung > 1 && $skug !== '-') {
                echo '

            <script>

                alert("SKU Toko Telah ada");

                window.location.href="?url=product";

            </script>';
            } else {
                $update2 = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$skug' WHERE id_toko='$idt'");
                header('location:?url=product');
            }
        } else {

            echo '

            <script>

                alert("Barang dan Gambar Tidak bisa di update");

                window.location.href="?url=product";

            </script>';
        }
    }
}

//mutasi
//mutasi
if (isset($_POST['mutasi'])) {
    $sku = $_POST['sku'];
    $idt = $_POST['idt'];
    $sku1 = $_POST['sku1'];
    $user = $_POST['user'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $jum = count($sku);

    for ($i = 0; $i < $jum; $i++) {
        $ambil = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko = '$sku[$i]'");
        $hitung = mysqli_num_rows($ambil);

        if ($hitung > 0) {
            echo '<script>
                alert("SKU sudah ada");
                window.location.href="?url=komponenlist";
            </script>';
        } else {
            $select = mysqli_query($conn, "SELECT sku_toko, id_product, berat, max_qty, tipe, per, tipe_barang, min_order, quantity_toko FROM toko_id WHERE sku_toko='$sku1[$i]'");
            $fetch = mysqli_fetch_assoc($select);
            $idp = $fetch['id_product'];
            $berat = $fetch['berat'];
            $maxqty = $fetch['max_qty'];
            $tipe = $fetch['tipe'];
            $per = $fetch['per'];
            $tipe_barang = $fetch['tipe_barang'];
            $min_order = $fetch['min_order'];
            $tipe_barang = $fetch['tipe_barang'];
            $quantity = $fetch['quantity_toko'];
            $ambilnama = mysqli_query($conn, "SELECT nama FROM product_toko_id WHERE id_product = '$idp'");
            $fetchnama = mysqli_fetch_assoc($ambilnama);
            $nama = $fetchnama['nama'];
            if ($sku[$i] == $sku1[$i]) {
                echo '<script>    
                    alert("Data SKU yang dimasukkan sama");
                    window.location.href="?url=product";
                </script>';
            } else {
                $mutasi = mysqli_query($conn, "INSERT INTO mutasitoko_id(id_toko, sku_lama, sku_baru, datetime) VALUES ('$idp', '$sku1[$i]', '$sku[$i]', '$date')");
                if ($mutasi) {
                    if (preg_match("/^(\d+)[a-z]+(\d+)$/i", $sku[$i], $matches)) {
                        $angkaSebelumHuruf = $matches[1];
                        $wadahId = $matches[2];
                        switch ($angkaSebelumHuruf) {
                            case '1':
                                $lorong = 1;
                                $toko = 'A';
                                $wadah = 'donat';
                                break;
                            case '2':
                                $lorong = 2;
                                $toko = 'A';
                                $wadah = 'donat';
                                break;
                            case '3':
                                $lorong = 2;
                                $toko = 'A';
                                $wadah = 'donat';
                                break;
                            case '4':
                                $lorong = 3;
                                $toko = 'A';
                                $wadah = 'donat';
                                break;
                            case '5':
                                $lorong = 1;
                                $toko = 'A';
                                $wadah = 'mika';
                                break;
                            case '6':
                                $lorong = 3;
                                $toko = 'A';
                                $wadah = 'mika';
                                break;
                            case '7':
                                $lorong = 4;
                                $toko = 'A';
                                $wadah = 'mika';
                                break;
                            case '8':
                                $lorong = 5;
                                $toko = 'B';
                                $wadah = 'kardus';
                                break;
                            case '9':
                                $lorong = 5;
                                $toko = 'B';
                                $wadah = 'donat';
                                break;
                            case '10':
                                $lorong = 5;
                                $toko = 'C';
                                $wadah = 'container';
                                break;
                            case '13':
                                $lorong = 5;
                                $toko = 'C';
                                $wadah = 'container';
                                break;
                            case '14':
                                $lorong = 5;
                                $toko = 'B';
                                $wadah = 'mika';
                                break;
                            default:
                                $lorong = 0;
                                $toko = 'B';
                                $wadah = 'default';
                                break;
                        }
                    }
                    $insert2 = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$sku[$i]', wadah = '$wadah', lorong = '$lorong', toko = '$toko', quantity_toko='0' WHERE id_toko='$idt[$i]'");
                    if ($insert2) {
                        $select = mysqli_query($conn, "SELECT id_mutasi FROM mutasitoko_id WHERE sku_lama = '$sku1[$i]' AND sku_baru = '$sku[$i]' ORDER BY datetime DESC LIMIT 1");
                        $assoc = mysqli_fetch_assoc($select);
                        $idm = $assoc['id_mutasi'];
                        if ($select) {
                            if (preg_match("/^(\d+)[a-z]+(\d+)$/i", $sku1[$i], $matches)) {
                                $angkaSebelumHuruf = $matches[1];
                                $wadahId = $matches[2];

                                // Tentukan kategori baru berdasarkan pola
                                switch ($angkaSebelumHuruf) {
                                    case '1':
                                        $lorong = 1;
                                        $toko = 'A';
                                        $wadah = 'donat';
                                        break;
                                    case '2':
                                        $lorong = 2;
                                        $toko = 'A';
                                        $wadah = 'donat';
                                        break;
                                    case '3':
                                        $lorong = 2;
                                        $toko = 'A';
                                        $wadah = 'donat';
                                        break;
                                    case '4':
                                        $lorong = 3;
                                        $toko = 'A';
                                        $wadah = 'donat';
                                        break;
                                    case '5':
                                        $lorong = 1;
                                        $toko = 'A';
                                        $wadah = 'mika';
                                        break;
                                    case '6':
                                        $lorong = 3;
                                        $toko = 'A';
                                        $wadah = 'mika';
                                        break;
                                    case '7':
                                        $lorong = 4;
                                        $toko = 'A';
                                        $wadah = 'mika';
                                        break;
                                    case '8':
                                        $lorong = 5;
                                        $toko = 'B';
                                        $wadah = 'kardus';
                                        break;
                                    case '9':
                                        $lorong = 5;
                                        $toko = 'B';
                                        $wadah = 'donat';
                                        break;
                                    case '10':
                                        $lorong = 5;
                                        $toko = 'C';
                                        $wadah = 'container';
                                        break;
                                    case '13':
                                        $lorong = 5;
                                        $toko = 'C';
                                        $wadah = 'container';
                                        break;
                                    case '14':
                                        $lorong = 5;
                                        $toko = 'B';
                                        $wadah = 'mika';
                                        break;
                                    default:
                                        $lorong = 0;
                                        $toko = 'B';
                                        $wadah = 'default';
                                        break;
                                }
                            }
                            $sql = mysqli_query($conn, "INSERT INTO toko_id(sku_toko, id_product, berat, max_qty, tipe, per, tipe_barang, lorong, toko, min_order, wadah, quantity_toko) VALUES ('$sku1[$i]', '$idp', '$berat', '$maxqty', '$tipe', '$per', '$tipe_barang', '$lorong', '$toko', '$min_order', '$wadah', '$quantity')");
                            if ($sql) {
                                $insert3 = mysqli_query($conn, "INSERT INTO task_id(id_history, requester, jenis, status) VALUES ('$idm', '$user', 'mutasi', 'unprocessed')");
                                if (!$insert3) {
                                    echo '<script>
                                        alert("Gagal memproses mutasi");
                                        window.location.href="?url=product";
                                    </script>';
                                }
                            } else {
                                echo '<script>
                                    alert("Gagal menambahkan SKU baru");
                                    window.location.href="?url=product";
                                </script>';
                            }
                        }
                    }
                }
            }
        }
    }
    header("Location:?url=product&sku=$nama");
}



//MUTASI ACC



if (isset($_POST['mutasiacc'])) {
    $cek = $_POST['cek'];
    $idt = $_POST['idt'];
    $idm = $_POST['idm'];
    $stat = $_POST['stat'];

    $jum = count($cek);

    for ($i = 0; $i < $jum; $i++) {
        $select = mysqli_query($conn, "SELECT sku_lama, sku_baru, id_toko AS idt FROM mutasitoko_id WHERE id_mutasi='$cek[$i]'");
        $data = mysqli_fetch_array($select);
        $skubaru = $data['sku_baru'];
        $skulama = $data['sku_lama'];
        $idtoko = $data['idt'];

        if ($select) {
            $update = mysqli_query($conn, "UPDATE toko_id SET sku_toko='$skubaru' WHERE id_toko='$idtoko'");
            if ($update) {
                $update1 = mysqli_query($conn, "UPDATE mutasitoko_id SET status_mutasi='$stat' WHERE id_mutasi='$cek[$i]'");
            }
        } else {
        }
    } {
    }
}

if (isset($_POST['newtoko'])) {
    $nama = $_POST['nama'];
    $jenis = $_POST['jenis'];
    $skug = $_POST['skug'];
    $jum = count($skug);
    $maxqty = $_POST['maxqty'];
    $berat = $_POST['berat'];
    $tipe = $_POST['tipe'];
    $per = $_POST['per'];
    $min_order = $_POST['minOrder'];
    $tipe_barang = $_POST['tipe_barang'];
    for ($i = 0; $i < $jum; $i++) {
        //gambar
        $allowed_extension = array('png', 'jpg', 'jpeg', 'svg', 'webp');
        $namaimage = $_FILES['file']['name'][$i]; //ambil gambar
        $dot = explode('.', $namaimage);
        $ekstensi = strtolower(end($dot)); //ambil ekstensi
        $ukuran = $_FILES['file']['size'][$i]; //ambil size
        $file_tmp = $_FILES['file']['tmp_name'][$i]; //lokasi
        if (preg_match("/^(\d+)[a-z]+(\d+)$/i", $skug[$i], $matches)) {
            $angkaSebelumHuruf = $matches[1];
            $wadahId = $matches[2];
            switch ($angkaSebelumHuruf) {
                case '1':
                case '2':
                case '3':
                case '4':
                    $wadah = 'donat';
                    break;
                case '5':
                case '6':
                case '7':
                case '14':
                    $wadah = 'mika';
                    break;
                case '8':
                    $wadah = 'kardus';
                    break;
                case '9':
                case '18':
                case '19':
                    $wadah = 'donat';
                    break;
                case '10':
                case '13':
                case '20';
                    $wadah = 'container';
                    break;
            }
            switch ($angkaSebelumHuruf) {
                case '1':
                case '2':
                case '3':
                case '4':
                case '5':
                case '6':
                case '7':
                case '15':
                    $toko = 'A';
                    break;
                case '8':
                case '9':
                case '11':
                case '12':
                case '14':
                case '16':
                case '17':
                    $toko = 'B';
                    break;
                case '10':
                case '13':
                case '18':
                case '19':
                case '20':
                case '21':
                    $toko = 'C';
                    break;
            }

            if ($angkaSebelumHuruf >= 1 && $angkaSebelumHuruf <= 7) {
                $lorong = $angkaSebelumHuruf;
            } elseif ($angkaSebelumHuruf == 8 || $angkaSebelumHuruf == 9) {
                $lorong = 5;
            } elseif ($angkaSebelumHuruf == 10 || $angkaSebelumHuruf == 13) {
                $lorong = 5;
            } elseif ($angkaSebelumHuruf == 14 || $angkaSebelumHuruf == 12) {
                $lorong = 5;
            }
        }
        //nama acak
        $image = md5(uniqid($namaimage, true) . time()) . '.' . $ekstensi; //compil
        //proses upload
        if (in_array($ekstensi, $allowed_extension) === true) {
            //validasi ukuran
            if ($ukuran > 0) {
                move_uploaded_file($file_tmp, '../../assets/img/' . $image);
                $insert = mysqli_query($conn, "INSERT INTO product_toko_id(image, nama, jenis) VALUES('$image','$nama[$i]','toko')");
                if ($insert) {
                    $select = mysqli_query($conn, "SELECT id_product FROM product_toko_id WHERE nama='$nama[$i]' LIMIT 1");
                    $data = mysqli_fetch_array($select);
                    $idp = $data['id_product'];
                    if ($select) {
                        $insertgudang = mysqli_query($conn, "INSERT INTO toko_id(id_product, sku_toko, lorong, toko, berat, max_qty, tipe, per, tipe_barang,min_order,wadah) VALUES('$idp','$skug[$i]', '$lorong', '$toko', '$berat[$i]', '$maxqty[$i]', '$tipe[$i]', '$per[$i]', '$tipe_barang[$i]','$min_order','$wadah')");
                        header('location:?url=product');
                    }
                } else {
                    // Handle insert error
                }
            } else {
                $insert = mysqli_query($conn, "INSERT INTO product_toko_id(image, nama, jenis) VALUES('$image','$nama[$i]','toko')");
                if ($insert) {
                    $select = mysqli_query($conn, "SELECT id_product FROM product_toko_id WHERE nama='$nama[$i]' LIMIT 1");
                    $data = mysqli_fetch_array($select);
                    $idp = $data['id_product'];
                    if ($select) {
                        $insertgudang = mysqli_query($conn, "INSERT INTO toko_id(id_product, sku_toko, lorong, toko, berat, max_qty, tipe, per, tipe_barang,min_order,wadah) VALUES('$idp','$skug[$i]', '$lorong', '$toko', '$berat[$i]', '$maxqty[$i]', '$tipe[$i]', '$per[$i]', '$tipe_barang[$i]','$min_order','$wadah')");
                        header('location:?url=product');
                    }
                } else {
                    // Handle insert error
                }
            }
        }
    }
}

if (isset($_POST['hapusitemsuper'])) {

    $skug = $_POST['skut'];
    $nama = $_POST['nama'];
    $idp = $_POST['idp'];
    $idt = $_POST['idt'];



    //gambar

    $allowed_extension = array('png', 'jpg', 'jpeg', 'svg', 'webp');

    $namaimage = $_FILES['file']['name']; //ambil gambar

    $dot = explode('.', $namaimage);

    $ekstensi = strtolower(end($dot)); //ambil ekstensi

    $ukuran = $_FILES['file']['size']; //ambil size

    $file_tmp = $_FILES['file']['tmp_name']; //lokasi



    //nama acak

    $image = md5(uniqid($namaimage, true) . time()) . '.' . $ekstensi; //compile

    if ($ukuran == 0) {

        $update = mysqli_query($conn, "DELETE FROM product_toko_id  WHERE id_product='$idp'");

        if ($update) {

            $select = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko='$skug'");

            $hitung = mysqli_num_rows($select);
            if ($hitung > 1 && $skug !== '-') {
                echo '

            <script>

                alert("SKU Toko Telah ada");

                window.location.href="?url=product";

            </script>';
            } else {
                $update2 = mysqli_query($conn, "DELETE FROM toko_id WHERE id_toko='$idt'");

                header('location:?url=product');
            }
        } else {

            echo '

            <script>

                alert("Barang Tidak bisa di update");

                window.location.href="?url=product";

            </script>';
        }
    } else {

        move_uploaded_file($file_tmp, '../assets/img/' . $image);

        $update = mysqli_query($conn, "DELETE FROM product_toko_id  WHERE id_product='$idp'");

        if ($update) {


            $select = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko='$skug'");
            $hitung = mysqli_num_rows($select);
            if ($hitung > 1 && $skug !== '-') {
                header('location:?url=product');
            } else {
                $update2 = mysqli_query($conn, "DELETE FROM toko_id WHERE id_toko='$idt'");

                header('location:?url=product');
            }
        } else {

            echo '

            <script>

                alert("Barang dan Gambar Tidak bisa di Hapus");

                window.location.href="?url=product";

            </script>';
        }
    }
}

// ds supertoko
if (isset($_POST['ds'])) {
    $invoice = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['skut'];
    $kurir = $_POST['kurir'];
    $requester = $_POST['requester'];
    $qty = $_POST['quantity'];
    $stat = 'approving';
    $jum = count($invoice);
    for ($i = 0; $i < $jum; $i++) {
        $select = mysqli_query($conn, "SELECT id_toko from toko_id where sku_toko = '$sku[$i]'");
        $data = mysqli_fetch_array($select);
        $idt = $data['id_toko'];
        if ($select) {
            $insert = mysqli_query($conn, "INSERT INTO ds_id(id_toko, invoice, resi, quantity,status,requester,kurir_ds) VALUES('$idt','$invoice[$i]', '$resi[$i]', '$qty[$i]','$stat','$requester','$kurir[$i]')");
            header('location:?url=ds');
        }
    }
}

if (isset($_POST['cekds'])) {
    $idds = $_POST['idds'];
    $user = $_POST['requester'];
    foreach ($idds as $id) {
        $select = mysqli_query($conn, "SELECT quantity, id_toko, output FROM ds_id WHERE id_ds = '$id' ");
        $data = mysqli_fetch_array($select);
        $quantityds = $data['quantity'];
        $id_product = $data['id_toko'];
        $output = $data['output'];

        if ($output == 'gudang') {
            $query = mysqli_query($conn, "UPDATE ds_id SET status='box', picker = '$user' WHERE id_ds = '$id'");
            if ($query) {
                header('Location: ?url=approveds');
            } else {
                echo 'Error updating record: ' . mysqli_error($conn);
            }
        } else {
            if ($select) {
                $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko='$id_product'");
                $datatoko = mysqli_fetch_array($selecttoko);
                $id_toko = $datatoko['id_toko'];
                $quantitytoko = $datatoko['quantity_toko'];

                $kurang = $quantitytoko - $quantityds;

                if ($selecttoko) {
                    $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$id $id_toko'");
                    $num = mysqli_num_rows($cektransaksi);

                    if ($num == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES('$id $id_toko','$quantitytoko','$kurang','ds order','$quantityds','$id_toko','$id')");
                        if ($insert) {
                            $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                            if ($updatetoko) {
                                $query = mysqli_query($conn, "UPDATE ds_id SET output='toko', status='box', picker = '$user' WHERE id_ds = '$id'");
                                if ($query) {
                                    header('Location: ?url=approveds');
                                } else {
                                    echo 'Error updating record: ' . mysqli_error($conn);
                                }
                            }
                        }
                    } else {
                        echo 'ada data yang sama masuk 2 kali';
                    }
                } else {
                }
            } else {
            }
        }
    }
}

if (isset($_POST['ubahds'])) {
    $idds = $_POST['idds'];
    $qty = $_POST['qty'];
    $skut = $_POST['skut'];

    $select = mysqli_query($conn, "SELECT id_toko from toko_id where sku_toko = '$skut'");
    $data = mysqli_fetch_array($select);
    $idt = $data['id_toko'];

    $update = mysqli_query($conn, "UPDATE ds_id SET quantity ='$qty', id_toko='$idt', status = 'approving' WHERE id_ds = '$idds'");
}

if (isset($_POST['caridata'])) {
    $skuToko = isset($_POST['sku']) ? $_POST['sku'] : '';

    // Query untuk mengambil data quantity total dari request
    $queryRequest = "SELECT MONTH(r.date) AS bulan, SUM(r.quantity_req) AS total_penjualan
              FROM request_id r
              INNER JOIN toko_id t ON r.id_toko = t.id_toko
              WHERE YEAR(r.date) = YEAR(CURDATE())
              AND r.type_req = 'request'
              AND t.sku_toko = '$skuToko'
              GROUP BY MONTH(r.date)
              ORDER BY bulan";

    $resultRequest = mysqli_query($conn, $queryRequest);

    // Query untuk menghitung jumlah id_request dengan type_req = refill
    $queryRefill = "SELECT MONTH(r.date) AS bulan, COUNT(r.id_request) AS total_refill
              FROM request_id r
              INNER JOIN toko_id t ON r.id_toko = t.id_toko
              WHERE YEAR(r.date) = YEAR(CURDATE())
              AND r.type_req = 'refill'
              AND t.sku_toko = '$skuToko'
              GROUP BY MONTH(r.date)
              ORDER BY bulan";

    $resultRefill = mysqli_query($conn, $queryRefill);

    $queryRequestCount = "SELECT MONTH(r.date) AS bulan, COUNT(r.id_request) AS total_request
              FROM request_id r
              INNER JOIN toko_id t ON r.id_toko = t.id_toko
              WHERE YEAR(r.date) = YEAR(CURDATE())
              AND r.type_req = 'request'
              AND t.sku_toko = '$skuToko'
              GROUP BY MONTH(r.date)
              ORDER BY bulan";

    $resultRequestCount = mysqli_query($conn, $queryRequestCount);

    // Inisialisasi array data bulan, quantity total dari request, dan jumlah id_request dengan type_req = refill
    $dataBulan = [];
    $dataRequest = [];
    $dataRefill = [];
    $datRequestCount = [];

    if (mysqli_num_rows($resultRequest) > 0) {
        while ($row = mysqli_fetch_assoc($resultRequest)) {
            $bulan = $row['bulan'];
            $penjualan = $row['total_penjualan'];
            $dataBulan[] = date("M", mktime(0, 0, 0, $bulan, 1)); // Konversi angka bulan menjadi format "M"
            $dataRequest[] = $penjualan;
        }
    }

    if (mysqli_num_rows($resultRefill) > 0) {
        while ($row = mysqli_fetch_assoc($resultRefill)) {
            $bulan = $row['bulan'];
            $refill = $row['total_refill'];
            $dataRefill[] = $refill;
        }
    }

    if (mysqli_num_rows($resultRequestCount) > 0) {
        while ($row = mysqli_fetch_assoc($resultRequestCount)) {
            $requestCount = $row['total_request'];
            $dataRequestCount[] = $requestCount;
        }
    }

    // Konversi array ke dalam format JSON
    $dataBulanJson = json_encode($dataBulan);
    $dataRequestJson = json_encode($dataRequest);
    $dataRefillJson = json_encode($dataRefill);
    $dataRequestCountJson = json_encode($dataRequestCount);
}


// prepare
if (isset($_POST['prepare'])) {
    // Ambil data dari form
    $idp = $_POST['idp'];
    $iduser = $_POST['iduser'];

    // Pastikan idp dan iduser adalah array dengan panjang yang sama
    if (is_array($idp) && is_array($iduser) && count($idp) === count($iduser)) {
        $jum = count($idp);

        // Loop untuk memproses setiap produk
        for ($i = 0; $i < $jum; $i++) {
            // Sanitasi data untuk mencegah SQL Injection
            $id_product = mysqli_real_escape_string($conn, $idp[$i]);
            $id_user = mysqli_real_escape_string($conn, $iduser[$i]);

            // Cek apakah data sudah ada di database
            $select = mysqli_query($conn, "SELECT * FROM toko_prepare WHERE id_product = '$id_product' AND status = 'unprocessed' AND id_user = '$id_user'");

            if (mysqli_num_rows($select) == 0) {
                // Insert jika data belum ada
                $insert = mysqli_query($conn, "INSERT INTO toko_prepare (id_product, id_user, status) VALUES ('$id_product', '$id_user', 'unprocessed')");
                if (!$insert) {
                    echo '<script>alert("Terjadi kesalahan saat menyimpan data!");</script>';
                }
            } else {
                // Jika data sudah ada
                echo '<script>alert("SKU TOKO SUDAH DI REQUEST");</script>';
            }
        }

        // Redirect setelah selesai
        header('location:?url=prepare');
        exit; // Pastikan script berhenti setelah redirect
    } else {
        echo '<script>alert("Data tidak valid! Pastikan ID produk dan ID user benar.");</script>';
    }
}


// import tokopedia
if (isset($_POST["import"])) {
    // Load Excel file
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach ($data as $row) {
        $invoice = $row[1];
        $pembayaran = $row[2];
        $status = $row[3];
        $nama_product = $row[8];
        $string = strlen($nama_product);
        $varian = substr($nama_product, $string - 20, 20);
        $sku_toko = strval($row[10]);
        $sku_toko2 = explode('-', $sku_toko)[0]; // Ambil hanya bagian pertama sebelum "-"
        $jumlah_dibeli = intval($row[13]);
        $penerima = $row[28];
        $kurir = $row[33];
        $tipe = $row[34];
        $resi = $row[35] ?: '';
        $tanggal_kirim = $row[36];
        $waktu_kirim = $row[37];
        $tgl_kirim = "$tanggal_kirim $waktu_kirim";
        $tanggal_kirim3 = date('Y-m-d H:i:s', strtotime(str_replace('/', ' ', $tgl_kirim)));
        if (strpos($invoice, 'INV') === 0) {
            if (strtotime($pembayaran) !== false) {
                $tanggal_bayar = (new DateTime($pembayaran))->format('Y-m-d H:i:s');
                $tanggal_kirim2 = (new DateTime($tanggal_kirim))->format('Y-m-d');
                $waktu_kirim2 = (new DateTime($waktu_kirim))->format('H:i:s');
                $stmt = $conn->prepare("SELECT id_product, id_toko, sku_toko FROM toko_id WHERE sku_toko = ?");
                $stmt->bind_param("s", $sku_toko2);
                $stmt->execute();
                $dataselect = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $id_toko = $dataselect ? $dataselect['id_toko'] : '0';
                if ($status === 'Menunggu Pickup' || ($kurir && $status === 'Pesanan Diproses')) {
                    $id_orderitem = "$invoice $sku_toko $varian";
                    // Check if record already exists
                    $stmt = $conn->prepare("SELECT id_orderitem FROM temporary_shop_id WHERE id_orderitem = ?");
                    $stmt->bind_param("s", $id_orderitem);
                    $stmt->execute();
                    $stmt->store_result();
                    $cancel = $stmt->num_rows;
                    $stmt->close();
                    if ($cancel == 0) {
                        $stmt = $conn->prepare("INSERT INTO temporary_shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, tipe, resi, tanggal_pengiriman, waktu_pengiriman, nama_product, olshop, status_mp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $olshop = "Tokopedia";
                        $stmt->bind_param("sssssssssssssss", $id_orderitem, $invoice, $tanggal_bayar, $id_toko, $sku_toko, $jumlah_dibeli, $penerima, $kurir, $tipe, $resi, $tanggal_kirim3, $waktu_kirim2, $nama_product, $olshop, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                // Update `tracking` table
                $stmt = $conn->prepare("UPDATE tracking SET status_mp = ?, no_resi = ? WHERE invoice = ?");
                $stmt->bind_param("sss", $status, $resi, $invoice);
                $stmt->execute();
                $stmt->close();
                // Update `shop_id` table
                $stmt = $conn->prepare("UPDATE shop_id SET status_mp = ?, resi = ? WHERE invoice = ?");
                $stmt->bind_param("sss", $status, $resi, $invoice);
                $stmt->execute();
                $stmt->close();
                // Check and insert into `history_tokped`
                $stmt = $conn->prepare("SELECT DISTINCT invoice, status_mp FROM shop_id WHERE invoice = ? AND olshop = 'Tokopedia'");
                $stmt->bind_param("s", $invoice);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $shopinvoice = $row['invoice'];
                    $status_mp = $row['status_mp'];
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT * FROM history_tokped WHERE invoice = ? AND status_terakhir = ?");
                    $stmt->bind_param("ss", $shopinvoice, $status_mp);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 0) {
                        $stmt->close();
                        $unique_id = "$shopinvoice $status $date";
                        $stmt = $conn->prepare("INSERT INTO history_tokped (unique_id, invoice, status_terakhir, date) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $unique_id, $invoice, $status, $date);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt->close();
                }
            } else {
                echo "Data pembayaran tidak valid: " . $pembayaran . "<br>";
            }
        }
    }
    header('location:?url=temporary');
}

// import shopee
if (isset($_POST["importshopee"])) {
    // Dapatkan informasi file yang diunggah
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    // Buka file Excel untuk dibaca
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    // Baca baris pertama sebagai nama header kolom
    $header = [];
    foreach ($worksheet->getRowIterator(1, 1) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false); // Loop semua sel, bahkan yang kosong
        foreach ($cellIterator as $cell) {
            $header[] = $cell->getValue();
        }
    }
    // Loop untuk membaca setiap baris dalam file Excel
    foreach ($worksheet->getRowIterator(2) as $row) {
        $data = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false); // Loop semua sel, bahkan yang kosong
        foreach ($cellIterator as $cell) {
            $data[] = $cell->getValue();
        }
        // Dapatkan data dari baris Excel
        $nopesanan = $data[array_search('No. Pesanan', $header)];
        $status = $data[array_search('Status Pesanan', $header)];
        $statusRetur = $data[array_search('Status Pembatalan/ Pengembalian', $header)];
        $resi = $data[array_search('No. Resi', $header)];
        $kurir = $data[array_search('Opsi Pengiriman', $header)];
        $tglkirim = $data[array_search('Waktu Pengiriman Diatur', $header)];
        $tglbayar = $data[array_search('Waktu Pembayaran Dilakukan', $header)];
        $nama_product = $data[array_search('Nama Produk', $header)];
        $string = strlen($nama_product);
        $varian = substr($nama_product, $string - 20, 20);
        $variasi = $data[array_search('Nama Variasi', $header)];
        $sku = $data[array_search('Nomor Referensi SKU', $header)];
        $jumlah = $data[array_search('Jumlah', $header)];
        $penerima = $data[array_search('Nama Penerima', $header)];
        $combinedStatus = $status . ' ' . $statusRetur;
        if (strpos($nopesanan, '2') === 0) {
            // Memeriksa apakah $pembayaran adalah datetime yang valid
            if ($tglbayar != '-') {
                $tanggal = new DateTime($tglkirim);
                $tanggal_kirim = $tanggal->format('Y-m-d H:i:s'); // Format tanggal ke dalam bentuk yang sesuai dengan MySQL
                $tanggal1 = new DateTime($tglbayar);
                $tanggal_bayar = $tanggal1->format('Y-m-d H:i:s');
                $select = mysqli_query($conn, "SELECT id_product, id_toko,sku_toko FROM toko_id WHERE sku_toko='$sku'");
                $dataselect = mysqli_fetch_array($select);
                if ($dataselect) {
                    $id_toko = $dataselect['id_toko'];
                } else {
                    $id_toko = '0';
                }
                if ($status == 'Perlu Dikirim' || $kurir == 'Instant-SPX Instant' && $status == 'Perlu Dikirim') {
                    $exceptshopee = mysqli_query($conn, "SELECT id_orderitem FROM temporary_shop_id WHERE id_orderitem = '$nopesanan $sku $varian $variasi' ");
                    $cancel = mysqli_num_rows($exceptshopee);
                    if ($cancel == 0) {
                        $stmt = $conn->prepare("INSERT INTO temporary_shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, resi, tanggal_pengiriman, nama_product, olshop, status_mp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $nopesanan_sku_varian_variasi = "$nopesanan $sku $varian $variasi";
                        $nama_product_variasi = "$nama_product ($variasi)";
                        $olshop = 'Shopee';
                        $stmt->bind_param("sssssssssssss", $nopesanan_sku_varian_variasi, $nopesanan, $tanggal_bayar, $id_toko, $sku, $jumlah, $penerima, $kurir, $resi, $tanggal_kirim, $nama_product_variasi, $olshop, $combinedStatus);
                        if ($stmt->execute()) {
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                    }
                }
            }
        }
        $update_tracking = "UPDATE tracking SET status_mp = '$combinedStatus', no_resi = '$resi' WHERE invoice = '$nopesanan'";
        $update_tracking_result = mysqli_query($conn, $update_tracking);
        if ($update_tracking_result) {
            $update_shop_id = "UPDATE shop_id SET status_mp = '$combinedStatus' WHERE invoice = '$nopesanan' AND sku_toko = '$sku'";
            $update_shop_id_result = mysqli_query($conn, $update_shop_id);
            if ($update_shop_id_result) {
                $update_resi_shop_id = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi' WHERE invoice = '$nopesanan'");
                if ($update_resi_shop_id) {
                    $selectkurir = mysqli_query($conn, "SELECT kurir FROM shop_id WHERE invoice = '$nopesanan'");
                    if (mysqli_num_rows($selectkurir) > 0) {
                        $assoc = mysqli_fetch_array($selectkurir);
                        if ($selectkurir && ($assoc['kurir'] == 'Reguler (Cashless)' || $assoc['kurir'] == 'Hemat') && $assoc['kurir'] != $kurir || $assoc['kurir'] == '') {
                            $update_kurir = mysqli_query($conn, "UPDATE shop_id SET kurir = '$kurir' WHERE invoice = '$nopesanan'");
                            if ($update_kurir) {
                                $select = mysqli_query($conn, "SELECT time_load FROM tracking WHERE invoice = '$nopesanan'");
                                $timeload_data = mysqli_fetch_assoc($select);
                                $time_load = $timeload_data['time_load'];

                                if (stripos($kurir, "JNE") !== false) {
                                    $namaKurir = 'JNE';
                                } else if (stripos($kurir, "Rekomendasi") !== false) {
                                    $namaKurir = 'rekomendasi';
                                } else if (stripos($kurir, "SiCepat") !== false || $kurir == 'Next Day-Sicepat BEST' || $kurir == 'Kargo-Sicepat Gokil') {
                                    $namaKurir = 'sicepat';
                                } else if (stripos($kurir, "j&t") !== false && (stripos($kurir, "Cargo") !== false || stripos($kurir, "Kargo") !== false)) {
                                    $namaKurir = 'j&t cargo';  // Prioritaskan untuk "j&t" dengan "Cargo" atau "Kargo"
                                } else if (stripos($kurir, "j&t") !== false) {
                                    $namaKurir = 'j&t';  // Untuk "j&t" tanpa "Cargo" atau "Kargo"
                                } else if (stripos($kurir, "Paxel") !== false) {
                                    $namaKurir = 'paxel';
                                } else if ($kurir == 'GTL(Regular)') {
                                    $namaKurir = 'gtl';
                                } else if ((stripos($kurir, "SPX") !== false || $kurir == 'Hemat') && stripos($kurir, 'Sameday') === false && stripos($kurir, 'Instant') === false) {
                                    $namaKurir = 'shopee';
                                } else {
                                    $namaKurir = NULL;
                                }
                                date_default_timezone_set('Asia/Jakarta');
                                $date = date('Y-m-d H:i:s');

                                if ($namaKurir == NULL) {
                                    $waktu = strtotime($date);
                                    $alert = strtotime('+2 hour', $waktu);
                                    $datesla = date('Y-m-d H:i:s', $alert);
                                    $namaKurir = 'instant';
                                } else {
                                    $sla = mysqli_query($conn, "SELECT deadline FROM schedule_id WHERE kurir = '$namaKurir'");
                                    $datasla = mysqli_fetch_assoc($sla);
                                    $alert = $datasla['deadline'];
                                    // Combine date from time_load and time from alert
                                    $datesla = new DateTime($time_load);
                                    $time_parts = explode(":", $alert);
                                    $datesla->setTime($time_parts[0], $time_parts[1], $time_parts[2]);
                                    $datesla = $datesla->format('Y-m-d H:i:s');
                                }

                                $exclude = mysqli_query($conn, "SELECT invoice FROM tracking WHERE invoice = '$nopesanan'");
                                $excluderows = mysqli_num_rows($exclude);
                                if ($excluderows > 0) {
                                    $update = mysqli_query($conn, "UPDATE tracking SET alert = '$datesla', nama_kurir = '$namaKurir' WHERE invoice = '$nopesanan'");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    header('location:?url=temporaryshopee');
}

// scan invoice admin
if (isset($_POST['adminresi'])) {
    $resiadmin = mysqli_real_escape_string($conn, $_POST['resiadmin']);
    $invoiceadmin = mysqli_real_escape_string($conn, $_POST['invoiceadmin']);
    $grup = mysqli_real_escape_string($conn, $_POST['grup']);
    $user = mysqli_real_escape_string($conn, $_POST['user']);
    $kurir = mysqli_real_escape_string($conn, $_POST['kurir']);
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $waktu = strtotime($date);
    $alert = strtotime('+1 hour', $waktu);
    $datetime = date('Y-m-d H:i:s', $alert);
    $select = mysqli_query($conn, "SELECT nama_kurir,invoice, no_resi, time_load FROM tracking WHERE (no_resi = '$resiadmin' AND invoice = '$invoiceadmin')");
    $data = mysqli_fetch_assoc($select);
    $kurir = $data['nama_kurir'];
    $time_load = $data['time_load'];
    // $cekdata = mysqli_query($conn, "SELECT time_load, id_tracking FROM tracking WHERE (no_resi = '$resiadmin' AND invoice = '$invoiceadmin')");
    if ($kurir ==  'JNE') {
        $namaKurir = 'JNE';
    } else if ($kurir == 'rekomendasi') {
        $namaKurir = $_POST['option'];
    } else if ($kurir == 'sicepat') {
        $namaKurir = 'sicepat';
    } else if ($kurir == 'j&t cargo') {
        $namaKurir = 'j&t cargo';
    } else if ($kurir == 'paxel') {
        $namaKurir = 'paxel';
    } else if ($kurir == 'gtl') {
        $namaKurir = 'gtl';
    } else if ($kurir == 'j&t') {
        $namaKurir = 'j&t';
    } else if ($kurir == 'shopee') {
        $namaKurir = 'shopee';
    } else if ($kurir == 'produksi') {
        $namaKurir = 'produksi';
    } else {
        $namaKurir = NULL;
    }
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    if ($namaKurir == NULL) {
        $waktu = strtotime($time_load);
        $alert = strtotime('+2 hour', $waktu);
        $datesla = date('Y-m-d H:i:s', $alert);
        $namaKurir = 'instant';
    } else {
        $sla = mysqli_query($conn, "SELECT deadline FROM schedule_id WHERE kurir = '$namaKurir'");
        $datasla = mysqli_fetch_assoc($sla);
        $alert = $datasla['deadline'];
        // Combine date from time_load and time from alert
        $datesla = new DateTime($time_load);
        $time_parts = explode(":", $alert); // Pisahkan jam, menit, detik
        $datesla->setTime($time_parts[0], $time_parts[1], $time_parts[2]);
        $datesla = $datesla->format('Y-m-d H:i:s'); // Format ulang ke string
    }

    $insert = mysqli_query($conn, "UPDATE tracking SET admin = 'check', waktu_admin = '$date', kelompok = '$grup', alert = '$datesla', user_admin = '$user', nama_kurir = '$namaKurir' WHERE (no_resi = '$resiadmin' AND invoice = '$invoiceadmin')");
    if ($insert) {
        // Success, do something if needed
    } else {
        // Handle insertion failure
    }
    header('location:?url=qr');
}

if (isset($_POST['invoice'])) {
    $ids = $_POST['ids'];
    $idp = $_POST['idp'];
    $qty = $_POST['qty'];
    $resi = $_POST['resi'];
    $invoices = $_POST['invoices'];
    $user = $_POST['user'];
    $cek = $_POST['req'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    echo 'kepanggil';

    foreach ($cek as $index => $selectedId) {
        $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko = '$idp[$index]'");
        $datatoko = mysqli_fetch_array($selecttoko);
        $quantitytoko = (int)$datatoko['quantity_toko'];
        $id_toko = $datatoko['id_toko'];
        $qtyRequested = (int)$qty[$index];
        if ($qtyRequested > $quantitytoko) {
            echo '
            <script>
                alert("Quantity tidak mencukupi untuk transaksi. Stok saat ini: ' . $quantitytoko . ', Quantity diminta: ' . $qtyRequested . '");
                window.location.href="?url=taskall";
            </script>';
            continue;
        }
        $kurang = $quantitytoko - $qtyRequested;
        if ($kurang >= 0) {
            if ($selecttoko) {
                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids[$index] $id_toko'");
                $num = mysqli_num_rows($cektransaksi);
                if ($num == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, uniq_id) VALUES('$ids[$index] $id_toko order','$quantitytoko','$kurang','order','$qtyRequested','$id_toko','$ids[$index]', '$user', '$id_toko $date')");

                    if ($insert) {
                        $updateshop = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'done', output = 'toko' where id_shop = '$ids[$index]'");

                        if ($updateshop) {
                            $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$idp[$index]'");
                            if ($update) {
                                $select = mysqli_query($conn, "SELECT invoice FROM shop_id WHERE invoice = '$invoices'");
                                $data = mysqli_num_rows($select);
                                $select2 = mysqli_query($conn, "SELECT invoice FROM shop_id WHERE invoice = '$invoices' AND status_pick = 'done'");
                                $cek = mysqli_num_rows($select2);

                                if ($data == $cek) {
                                    mysqli_query($conn, "UPDATE tracking SET picking = 'check', waktu_picking = '$date' WHERE invoice = '$invoices'");
                                } else {
                                    mysqli_query($conn, "UPDATE tracking SET picking = 'pending' WHERE invoice = '$invoices'");
                                }
                            }
                        }
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
            }
        } else {
            echo '          
            <script>
            
            alert("quantity ini tidak mencukupi untuk transaksi");
            
            window.location.href="?url=taskall";
            
            </script>';
        }
    }

    if (empty($cek)) {
        $allDone = mysqli_query($conn, "SELECT COUNT(*) as count_done FROM shop_id WHERE invoice = '$invoices' AND status_pick = 'done'");
        $countDone = mysqli_fetch_assoc($allDone)['count_done'];

        $selectShopCount = mysqli_query($conn, "SELECT COUNT(*) as count_shop FROM shop_id WHERE invoice = '$invoices'");
        $countShop = mysqli_fetch_assoc($selectShopCount)['count_shop'];

        if ($countDone == $countShop) {
            mysqli_query($conn, "UPDATE tracking SET picking = 'check', waktu_picking = '$date' WHERE invoice = '$invoices'");
        } else {
            mysqli_query($conn, "UPDATE tracking SET picking = 'pending' WHERE invoice = '$invoices'");
        }
    }

    header('location:?url=taskall');
}


if (isset($_POST['save'])) {
    $ids = $_POST['ids'];
    $idp = $_POST['idp'];
    $qty = $_POST['qty'];
    $resi = $_POST['resi'];
    $invoices = $_POST['invoices'];
    $user = $_POST['user'];
    $cek = $_POST['req'];
    $user = $_POST['user'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach ($cek as $index => $selectedId) {
        $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko = '$idp[$index]'");
        $datatoko = mysqli_fetch_array($selecttoko);
        $quantitytoko = $datatoko['quantity_toko'];
        $id_toko = $datatoko['id_toko'];
        $kurang = $quantitytoko - $qty[$index];
        if ($kurang >= 0) {
            if ($selecttoko) {
                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids[$index] $id_toko'");
                $num = mysqli_num_rows($cektransaksi);

                if ($num == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user) VALUES('$ids[$index] $id_toko order','$quantitytoko','$kurang','order','$qty[$index]','$id_toko','$ids[$index]', '$user')");
                    if ($insert) {
                        $updateshop = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'done', output = 'toko' where id_shop = '$ids[$index]'");
                        if ($updateshop) {
                            $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$idp[$index]'");
                        } else {
                        }
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
            }
        }
    }
    header('location:?url=taskall');
}


if (isset($_POST['request'])) {
    $ids = $_POST['ids'];
    $btn = $_POST['request'];
    $hari_ini = date('Y-m-d');
    $idp = $_POST['idp'];
    $qty = $_POST['qty'];
    $invoices = $_POST['inv'];
    $user = $_POST['user'];
    $note = $_POST['note'];
    foreach ($btn as $index => $selectedId) {
        $select = mysqli_query($conn, "SELECT kurir FROM shop_id WHERE id_shop = '$ids[$index]'");
        $data = mysqli_fetch_assoc($select);
        $kurir = $data['kurir'];
        if ($kurir == 'GoSend(Instant 3 Jam)' || $kurir == 'GoSend(Same Day 8 Jam)' || $kurir == 'AnterAja(Same Day)' || $kurir == 'Instant-SPX Instant' || $kurir == 'Same Day-GrabExpress Sameday' || $kurir == 'GrabExpress(Same Day 8 Jam)' || $kurir == 'GrabExpress(Instant 3 Jam)' || $kurir == 'Kargo-J&T Cargo' || $kurir == 'Same Day-GoSend Same Day' || $kurir == 'GTL(Sameday)' || $kurir == 'Same Day-SPX Sameday' || $kurir == 'instant') {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' where id_shop = '$ids[$index]'");
            if ($insert) {
                $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp[$index] $invoices'");
                $hitung = mysqli_num_rows($cek);
                if ($hitung == 0) {
                    $req = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester,status_req,note) VALUES ('$idp[$index] $invoices','$idp[$index]', '$invoices', '$qty[$index]', 'Request', 'Instant', '$user[$index]','unprocessed','$note[$index]')");
                    if ($req) {
                        $query = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish = '$idp[$index]'");
                        while ($data = mysqli_fetch_assoc($query)) {
                            $idkomp = $data['id_komponen'];
                            $table = ($idkomp < 2000000) ? 'product_mateng_id' : 'product_mentah_id';
                            $inventory = ($idkomp < 2000000) ? 'mateng_id' : 'gudang_id';
                            $select1 = mysqli_query($conn, "SELECT COUNT(id_gudang) as total FROM $inventory WHERE id_product = '$idkomp'");
                            $data1 = mysqli_fetch_assoc($select1);
                            $total = $data1['total'];
                            $select2 = mysqli_query($conn, "SELECT COUNT(id_gudang) as so FROM $inventory WHERE id_product = '$idkomp' AND stock_opname >= $hari_ini");
                            $data2 = mysqli_fetch_assoc($select2);
                            $so = $data2['so'];
                            if ($total == $so) {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'finish' WHERE id_product = '$idkomp'");
                            } else {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'unfinished' WHERE id_product = '$idkomp'");
                            }
                        }
                    } else {
                        echo 'Error updating record: ' . mysqli_error($conn);
                    }
                }
            }
        } else if ($kurir == 'JNE(Reguler)' || $kurir == 'JNE(YES)' || $kurir == 'Kargo-JNE Trucking (JTR)' || $kurir == 'Reguler (Cashless)-JNE Reguler') {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' where id_shop = '$ids[$index]'");
            if ($insert) {
                $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp[$index] $invoices'");
                $hitung = mysqli_num_rows($cek);
                if ($hitung == 0) {
                    $req = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester,status_req,note) VALUES ('$idp[$index] $invoices','$idp[$index]', '$invoices', '$qty[$index]', 'Request', 'Reguler (JNE)', '$user[$index]','unprocessed','$note[$index]')");
                    if ($req) {
                        $query = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish = '$idp[$index]'");
                        while ($data = mysqli_fetch_assoc($query)) {
                            $idkomp = $data['id_komponen'];
                            $table = ($idkomp < 2000000) ? 'product_mateng_id' : 'product_mentah_id';
                            $inventory = ($idkomp < 2000000) ? 'mateng_id' : 'gudang_id';
                            $select1 = mysqli_query($conn, "SELECT COUNT(id_gudang) as total FROM $inventory WHERE id_product = '$idkomp'");
                            $data1 = mysqli_fetch_assoc($select1);
                            $total = $data1['total'];
                            $select2 = mysqli_query($conn, "SELECT COUNT(id_gudang) as so FROM $inventory WHERE id_product = '$idkomp' AND stock_opname >= $hari_ini");
                            $data2 = mysqli_fetch_assoc($select2);
                            $so = $data2['so'];
                            if ($total == $so) {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'finish' WHERE id_product = '$idkomp'");
                            } else {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'unfinished' WHERE id_product = '$idkomp'");
                            }
                        }
                    }
                }
            }
        } else {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' where id_shop = '$ids[$index]'");
            if ($insert) {
                $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp[$index] $invoices'");
                $hitung = mysqli_num_rows($cek);
                if ($hitung == 0) {
                    $req = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester,status_req,note) VALUES ('$idp[$index] $invoices','$idp[$index]', '$invoices', '$qty[$index]', 'Request', 'Reguler', '$user[$index]','unprocessed','$note[$index]')");
                    if ($req) {
                        $query = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish = '$idp[$index]'");
                        while ($data = mysqli_fetch_assoc($query)) {
                            $idkomp = $data['id_komponen'];
                            $table = ($idkomp < 2000000) ? 'product_mateng_id' : 'product_mentah_id';
                            $inventory = ($idkomp < 2000000) ? 'mateng_id' : 'gudang_id';
                            $select1 = mysqli_query($conn, "SELECT COUNT(id_gudang) as total FROM $inventory WHERE id_product = '$idkomp'");
                            $data1 = mysqli_fetch_assoc($select1);
                            $total = $data1['total'];
                            $select2 = mysqli_query($conn, "SELECT COUNT(id_gudang) as so FROM $inventory WHERE id_product = '$idkomp' AND stock_opname >= $hari_ini");
                            $data2 = mysqli_fetch_assoc($select2);
                            $so = $data2['so'];
                            if ($total == $so) {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'finish' WHERE id_product = '$idkomp'");
                            } else {
                                $task = mysqli_query($conn, "UPDATE $table SET task = 'unfinished' WHERE id_product = '$idkomp'");
                            }
                        }
                    }
                }
            }
        }
    }
    header('location:?url=detailpending&noresi=' . $invoices . '');
}


if (isset($_POST['refill'])) {
    $ids = $_POST['ids'];
    $btn = $_POST['refill'];
    $idp = $_POST['idp'];
    $qty = $_POST['qty'];
    $hari_ini = date('Y-m-d');
    $resi = $_POST['resi'];
    $user = $_POST['user'];
    $tipe = $_POST['tipe'];
    $invoices = $_POST['inv'];
    $date = date('Y-m-d H:i:s');
    foreach ($btn as $index => $selectedId) {
        $select = mysqli_query($conn, "SELECT kurir FROM shop_id WHERE id_shop = '$ids[$index]'");
        $data = mysqli_fetch_assoc($select);
        $kurir = $data['kurir'];
        if ($kurir == 'GoSend(Instant 3 Jam)' || $kurir == 'GoSend(Same Day 8 Jam)' || $kurir == 'AnterAja(Same Day)' || $kurir == 'Instant-SPX Instant' || $kurir == 'Same Day-GrabExpress Sameday') {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending' where id_shop = '$ids[$index]'");
            if ($insert) {
                $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp[$index] $date'");
                $hitung = mysqli_num_rows($cek);
                if ($hitung == 0) {
                    $cekrefill = mysqli_query($conn, "SELECT COUNT(*) AS request_count 
                                                        FROM request_id 
                                                        WHERE id_toko = '$idp[$index]' 
                                                        AND (status_req = 'unprocessed' OR  status_req = 'On Process')
                                                        AND type_req = 'refill'");
                    $request_count_data = mysqli_fetch_assoc($cekrefill);
                    $request_count = $request_count_data['request_count'];
                    if ($request_count == 0) {
                        $req = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, quantity_req, type_req, requester,status_req,status_toko) VALUES ('$idp[$index] $date','$idp[$index]', '$qty[$index]', 'refill','$user[$index]','unprocessed','on process toko')");
                        if ($req) {
                            $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'On Process' WHERE id_toko = '$idp[$index]' AND status = 'unprocessed'");
                            $query = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish = '$idp[$index]'");
                            while ($data = mysqli_fetch_assoc($query)) {
                                $idkomp = $data['id_komponen'];
                                $table = ($idkomp < 2000000) ? 'product_mateng_id' : 'product_mentah_id';
                                $inventory = ($idkomp < 2000000) ? 'mateng_id' : 'gudang_id';
                                $select1 = mysqli_query($conn, "SELECT COUNT(id_gudang) as total FROM $inventory WHERE id_product = '$idkomp'");
                                $data1 = mysqli_fetch_assoc($select1);
                                $total = $data1['total'];
                                $select2 = mysqli_query($conn, "SELECT COUNT(id_gudang) as so FROM $inventory WHERE id_product = '$idkomp' AND stock_opname >= $hari_ini");
                                $data2 = mysqli_fetch_assoc($select2);
                                $so = $data2['so'];
                                if ($total == $so) {
                                    $task = mysqli_query($conn, "UPDATE $table SET task = 'finish' WHERE id_product = '$idkomp'");
                                } else {
                                    $task = mysqli_query($conn, "UPDATE $table SET task = 'unfinished' WHERE id_product = '$idkomp'");
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending' where id_shop = '$ids[$index]'");
            if ($insert) {
                $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp[$index] $date'");
                $hitung = mysqli_num_rows($cek);
                if ($hitung == 0) {
                    $cekrefill = mysqli_query($conn, "SELECT COUNT(*) AS request_count 
                    FROM request_id 
                    WHERE id_toko = '$idp[$index]' 
                    AND (status_req = 'unprocessed' OR  status_req = 'On Process')
                    AND type_req = 'refill'");
                    $request_count_data = mysqli_fetch_assoc($cekrefill);
                    $request_count = $request_count_data['request_count'];
                    if ($request_count == 0) {
                        $req = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, type_req, requester,status_req, status_toko) VALUES ('$idp[$index] $date','$idp[$index]', 'refill','$user[$index]','unprocessed', 'on process toko')");
                        if ($req) {
                            $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'On Process' WHERE id_toko = '$idp[$index]' AND status = 'unprocessed'");
                            $query = mysqli_query($conn, "SELECT id_komponen FROM list_komponen WHERE id_product_finish = '$idp[$index]'");
                            while ($data = mysqli_fetch_assoc($query)) {
                                $idkomp = $data['id_komponen'];
                                $table = ($idkomp < 2000000) ? 'product_mateng_id' : 'product_mentah_id';
                                $inventory = ($idkomp < 2000000) ? 'mateng_id' : 'gudang_id';
                                $select1 = mysqli_query($conn, "SELECT COUNT(id_gudang) as total FROM $inventory WHERE id_product = '$idkomp'");
                                $data1 = mysqli_fetch_assoc($select1);
                                $total = $data1['total'];
                                $select2 = mysqli_query($conn, "SELECT COUNT(id_gudang) as so FROM $inventory WHERE id_product = '$idkomp' AND stock_opname >= $hari_ini");
                                $data2 = mysqli_fetch_assoc($select2);
                                $so = $data2['so'];
                                if ($total == $so) {
                                    $task = mysqli_query($conn, "UPDATE $table SET task = 'finish' WHERE id_product = '$idkomp'");
                                } else {
                                    $task = mysqli_query($conn, "UPDATE $table SET task = 'unfinished' WHERE id_product = '$idkomp'");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    header('location:?url=detailpending&noresi=' . $invoices . '');
}

// cancel request
if (isset($_POST['cancel'])) {
    $ids = $_POST['ids'];
    $btn = $_POST['cancel'];
    $invoices = $_POST['inv'];
    foreach ($btn as $index => $selectedId) {
        $selesct = mysqli_query($conn, "SELECT id_product,invoice FROM shop_id WHERE id_shop = '$ids[$index]'");
        $datatoko = mysqli_fetch_assoc($selesct);
        $idpt = $datatoko['id_product'];
        $inv = $datatoko['invoice'];

        $update = mysqli_query($conn, "UPDATE request_id SET uniq_idreq ='$idpt $inv cancel', status_req = 'di cancel toko', invoice = '$invoices cancel' WHERE invoice = '$inv' AND id_toko = '$idpt'");
        if ($update) {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = '', output = '' where id_shop = '$ids[$index]'");
            if ($insert) {
            }
        }
    }
}


if (isset($_POST['reqds'])) {
    $idp = $_POST['reqds'];
    $name = $_POST['pick'];
    $inv = $_POST['inv'];
    $date = date('Y-m-d H:i:s');
    $select = mysqli_query($conn, "SELECT quantity FROM ds_id WHERE invoice='$inv' AND id_toko='$idp'");
    $data = mysqli_fetch_assoc($select);
    $qty = $data['quantity'];
    if ($select) {
        $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$idp $date'");
        $hitung = mysqli_num_rows($cek);
        if ($hitung == 0) {
            $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq,id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester,status_req) VALUES ('$idp $inv','$idp', '$inv', '$qty', 'Request', 'Regular', '$name','unprocessed')");
            mysqli_query($conn, "UPDATE ds_id SET status='pickingpend', picker = '$name', output='gudang' WHERE invoice='$inv' AND id_toko='$idp'");
        }
    }
}
// pending
if (isset($_POST['pending'])) {
    $ids = $_POST['idst'];
    $idp = $_POST['idpt'];
    $qty = $_POST['qtyt'];
    $resi = $_POST['resit'];
    $invoices = $_POST['invoicest'];
    $user = $_POST['usert'];
    $cek = $_POST['req'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach ($cek as $index => $selectedId) {
        $select = mysqli_query($conn, "SELECT id_product, jumlah, output FROM shop_id WHERE id_shop = '$selectedId'");
        $data = mysqli_fetch_assoc($select);
        $idp = $data['id_product'];
        $jumlah = $data['jumlah'];
        $output = $data['output'];
        if ($output == 'toko') {
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko='$idp'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantitytoko = $datatoko['quantity_toko'];
            $id_toko = $datatoko['id_toko'];
            $kurang = $quantitytoko - $jumlah;
            if ($kurang >= 0) {
                if ($selecttoko) {
                    $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids[$index] $id_toko'");
                    $num = mysqli_num_rows($cektransaksi);
                    if ($num == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history,uniq_id, nama_user) VALUES('$ids[$index] $id_toko order','$quantitytoko','$kurang','order','$jumlah','$id_toko','$ids[$index]','$ids[$index] $date', '$user')");
                        if ($insert) {
                            $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'done' WHERE id_shop = '$ids[$index]'");
                        }
                    } else {
                        echo 'ada data yang sama masuk 2 kali';
                    }
                } else {
                }
            } else {
                echo '
                    <script>
                    alert("quantity ini tidak mencukupi untuk transaksi ini");
                    window.location.href="?url=pending";
                    </script>';
            }
        } elseif ($output == '') {
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko='$idp'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantitytoko = $datatoko['quantity_toko'];
            $id_toko = $datatoko['id_toko'];
            $kurang = $quantitytoko - $jumlah;
            if ($kurang >= 0) {
                $insert = mysqli_query($conn, "UPDATE shop_id SET output = 'toko' WHERE id_shop = '$ids[$index]'");
                if ($selecttoko) {
                    $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids[$index] $id_toko'");
                    $num = mysqli_num_rows($cektransaksi);
                    if ($num == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history,uniq_id, nama_user) VALUES('$ids[$index] $id_toko order','$quantitytoko','$kurang','order','$jumlah','$id_toko','$ids[$index]','$ids[$index] $date', '$user')");
                        if ($insert) {
                            $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'done' WHERE id_shop = '$ids[$index]'");
                        }
                    } else {
                        echo 'ada data yang sama masuk 2 kali';
                    }
                } else {
                }
            } else {
                echo '
                    
                    <script>
                    
                    alert("quantity ini tidak mencukupi untuk transaksi ini");
                    
                    window.location.href="?url=pending";
                    
                    </script>';
            }
        } else {
            $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'done' WHERE id_shop = '$ids[$index]'");
        }

        $selectCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM shop_id WHERE invoice = '$invoices'");
        $totalCount = mysqli_fetch_assoc($selectCount)['total'];
        $selectDoneCount = mysqli_query($conn, "SELECT COUNT(*) AS totalDone FROM shop_id WHERE invoice = '$invoices' AND status_pick = 'done'");
        $doneCount = mysqli_fetch_assoc($selectDoneCount)['totalDone'];

        if ($totalCount == $doneCount) {
            mysqli_query($conn, "UPDATE tracking SET picking = 'check', waktu_picking = '$date' WHERE invoice = '$invoices'");
        }
    }
    header('location:?url=pending');
}



//Approve Request Gudang
if (isset($_POST['approverequest'])) {
    $check = $_POST['check'];
    $note = $_POST['note'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $count = count($check);
    for ($i = 0; $i < $count; $i++) {
        $select = mysqli_query($conn, "SELECT id_product, id_gudang, quantity_request FROM request_toko WHERE id_request='$check[$i]'");
        $data = mysqli_fetch_array($select);
        $id_product = $data['id_product'];
        $id_gudang = $data['id_gudang'];
        if ($id_gudang == 0) {
        } else {
            $table = ($id_gudang < 1000000) ? 'mateng_id' : 'gudang_id';
        }
        $quantity_request = $data['quantity_request'];
        if ($select) {
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko, id_product FROM toko_id WHERE id_toko='$id_product'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantity_toko = $datatoko['quantity_toko'];
            $id_toko = $datatoko['id_toko'];
            $idProduct = $datatoko['id_product'];
            $kurang = $quantity_toko - $quantity_request;
            if ($selecttoko) {
                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$check[$i] $id_toko'");
                $num = mysqli_num_rows($cektransaksi);
                if ($num == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES('$check[$i] $id_toko request gudang','$quantity_toko','$kurang','request gudang','$quantity_request','$id_toko','$check[$i]')");
                    if ($id_gudang != 0) {
                        $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                        if ($updatetoko) {
                            $select_komponen = mysqli_query($conn, "(SELECT sku_gudang, id_komponen, nama, image, quantity_komponen, gudang_id.quantity 
                                                                       FROM list_komponen 
                                                                       JOIN product_mentah_id ON list_komponen.id_komponen = product_mentah_id.id_product 
                                                                       JOIN gudang_id ON gudang_id.id_product = product_mentah_id.id_product  
                                                                       WHERE list_komponen.id_product_finish = '$idProduct' AND gudang_id.id_gudang='$id_gudang') 
                                                                       UNION ALL 
                                                                       (SELECT sku_gudang, id_komponen, nama, image, quantity_komponen, mateng_id.quantity
                                                                       FROM list_komponen 
                                                                       JOIN product_mateng_id ON list_komponen.id_komponen = product_mateng_id.id_product 
                                                                       JOIN mateng_id ON mateng_id.id_product = product_mateng_id.id_product 
                                                                       WHERE list_komponen.id_product_finish = '$idProduct' AND mateng_id.id_gudang='$id_gudang')");
                            while ($data_komponen = mysqli_fetch_array($select_komponen)) {
                                $quantity_komponen = $data_komponen['quantity_komponen'];
                                $quantity_gudang = $data_komponen['quantity'];
                                $kali = $quantity_komponen * $quantity_request;
                                $tambah = $quantity_gudang + $kali;
                                $history = mysqli_query($conn, "INSERT INTO transaksi_gudang(uniq_transaksi,stok_sebelum,stok_sesudah,jenis_transaksi,jumlah,id_gudang,id_pengurang, note) VALUES ('$check[$i] $id_gudang $date','$quantity_gudang','$tambah','request gudang','$kali','$id_gudang','$check[$i]', '$note[$i]') ");
                                if ($history) {
                                    $update = mysqli_query($conn, "UPDATE $table SET quantity = '$tambah' WHERE id_gudang = '$id_gudang'");
                                    if ($update) {
                                        $updatereq = mysqli_query($conn, "UPDATE request_toko SET status_request = 'Approved' WHERE id_request = '$check[$i]'");
                                    }
                                }
                            }
                        }
                    } else {
                        $updatereq = mysqli_query($conn, "UPDATE request_toko SET status_request = 'Approved' WHERE id_request = '$check[$i]'");
                        if ($updatereq) {
                            $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                        }
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
            }
        } else {
            echo 'Gagal';
        }
    }
    header("location:?url=alltransaksi");
}

// refund pending supertoko
if (isset($_POST['refund_pending'])) {
    $ids = $_POST['ids'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];
    $nama_item = $_POST['nama_item'];
    $idp = $_POST['idp'];
    $note = $_POST['note'];
    $refund = mysqli_query($conn, "INSERT INTO refund_id (id_shop,invoice,resi,nama_product,sku_awal,jumlah_awal,id_product_awal,status) VALUES ('$ids','$inv','$resi','$nama_item','$sku','$jumlah','$idp','Refund')");
    if ($refund) {
        $selectrefund = mysqli_query($conn, "SELECT status_pick FROM shop_id WHERE id_shop='$ids'");
        $data = mysqli_fetch_array($selectrefund);

        if ($status_pick == 'done') {
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko FROM toko_id WHERE id_toko='$idp'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantitytoko = $datatoko['quantity_toko'];
            $id_toko = $datatoko['id_toko'];
            $tambah = $quantitytoko + $jumlah;

            if ($selecttoko) {
                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids $id_toko refund'");
                $num = mysqli_num_rows($cektransaksi);

                if ($num == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES('$ids $id_toko refund','$quantitytoko','$tambah','refund','$jumlah','$id_toko','$ids')");
                    if ($insert) {
                        $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$tambah' WHERE id_toko='$id_toko'");
                        if ($updatetoko) {
                            $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Refund' WHERE invoice = '$inv'");
                            header('location:?url=detailpending&noresi=' . $inv . '');
                        }
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
            } else {
            }
        } else {
            $pending = mysqli_query($conn, "UPDATE shop_id SET status_order = 'Refund', jumlah = '0', note_refund = '$note' WHERE id_shop = '$ids'");
            if ($pending) {
                $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Refund' WHERE invoice = '$inv'");
                header('location:?url=detailpending&noresi=' . $inv . '');
            }
        }
    }
}

// edit pending supertoko
if (isset($_POST['edit_pending'])) {
    $ids = $_POST['ids'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];
    $note = $_POST['note'];

    $select = mysqli_query($conn, "SELECT status_pick,nama_product,sku_toko,id_product,jumlah FROM shop_id WHERE id_shop = '$ids'");
    $data = mysqli_fetch_assoc($select);
    $namalama = $data['nama_product'];
    $status = $data['status_pick'];
    $skulama = $data['sku_toko'];
    $idplama = $data['id_product'];
    $jumlahlama = $data['jumlah'];

    if ($status == 'done') {
        if ($skulama == $sku) {
            $selisih = $jumlahlama - $jumlah;
            if ($data) {
                $ambil = mysqli_query($conn, "SELECT quantity_toko,nama,toko_id.id_toko FROM toko_id,product_toko_id WHERE sku_toko = '$sku' AND product_toko_id.id_product = toko_id.id_product");
                $list = mysqli_fetch_assoc($ambil);
                $namabaru = $list['nama'];
                $qty = $list['quantity_toko'];
                $hasil = $qty + $selisih;
                $idpbaru = $list['id_toko'];
                if ($list) {
                    $insert = mysqli_query($conn, "INSERT INTO refund_id (id_shop,invoice,resi,nama_product,sku_awal,jumlah_awal,id_product_awal,nama_product_ganti,sku_ganti,jumlah_ganti,id_product_ganti,status) VALUES ('$ids','$inv','$resi','$namalama','$skulama','$jumlahlama','$idplama','$namabaru','$sku','$jumlah','$idpbaru','Changed')");
                    if ($insert) {
                        $update = mysqli_query($conn, "UPDATE shop_id SET nama_product = '$namabaru' , sku_toko = '$sku', jumlah = '$jumlah' , id_product = '$idpbaru', status_order = 'Changed' WHERE id_shop = '$ids'");
                        if ($update) {
                            $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Changed' WHERE invoice = '$inv'");
                            $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids $idpbaru $jumlah'");
                            $num = mysqli_num_rows($cektransaksi);

                            if ($num == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES('$ids $idpbaru $jumlah refund','$qty','$hasil','refund','$selisih','$idpbaru','$ids')");
                                if ($insert) {
                                    $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$hasil' WHERE id_toko='$idpbaru'");
                                    if ($updatetoko) {
                                        $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Changed' WHERE invoice = '$inv'");
                                        header('location:?url=detailpending&noresi=' . $inv . '');
                                    }
                                }
                            } else {
                                echo 'ada data yang sama masuk 2 kali';
                            }
                            header('location:?url=detailpending&noresi=' . $inv . '');
                        }
                    }
                }
            }
        }
    } else {
        if ($data) {
            $ambil = mysqli_query($conn, "SELECT nama,toko_id.id_toko FROM toko_id,product_toko_id WHERE sku_toko = '$sku' AND product_toko_id.id_product = toko_id.id_product");
            $list = mysqli_fetch_assoc($ambil);
            $namabaru = $list['nama'];
            $idpbaru = $list['id_toko'];
            if ($list) {
                $insert = mysqli_query($conn, "INSERT INTO refund_id (id_shop,invoice,resi,nama_product,sku_awal,jumlah_awal,id_product_awal,nama_product_ganti,sku_ganti,jumlah_ganti,id_product_ganti,status) VALUES ('$ids','$inv','$resi','$namalama','$skulama','$jumlahlama','$idplama','$namabaru','$sku','$jumlah','$idpbaru','Changed')");
                if ($insert) {
                    $update = mysqli_query($conn, "UPDATE shop_id SET nama_product = '$namabaru' , sku_toko = '$sku', jumlah = '$jumlah' , id_product = '$idpbaru', status_order = 'Changed', note_refund = '$note' WHERE id_shop = '$ids'");
                    if ($update) {
                        $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Changed' WHERE invoice = '$inv'");
                        header('location:?url=detailpending&noresi=' . $inv . '');
                    }
                }
            }
        }
    }
}

if (isset($_POST['refund'])) {
    $ids = $_POST['ids'];
    $btn = $_POST['refund'];
    $inv = $_POST['invoices'];
    foreach ($btn as $index => $selectedId) {
        $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'toko' where id_shop = '$ids[$index]'");
        if ($insert) {
            header('location:?url=resi&noresi=' . $inv . '');
        }
    }
}

//CancelOrder
if (isset($_POST['cancel_order'])) {
    $inv = $_POST['inv'];
    $update = mysqli_query($conn, "UPDATE tracking SET refaund = 'cancel', admin = 'cancel' , picking = 'cancel', box = 'cancel', checking = 'cancel', dikurir = 'cancel', packing = 'cancel' WHERE invoice = '$inv'");
    if ($update) {
        $updateshop = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE invoice = '$inv'");
        if ($updateshop) {
            header('location:?url=invoice&sku=' . $inv . '');
        }
    }
}

if (isset($_POST['canceltoko'])) {
    $inv = $_POST['inv'];
    $ids = $_POST['ids'];
    $qty = $_POST['jumlah'];
    $idp = $_POST['idp'];
    $user = $_POST['user'];
    $sku =  $_POST['sku'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids'");
    if ($update) {
        $select = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko = '$idp'");
        $list = mysqli_fetch_array($select);
        $qtytoko = $list['quantity_toko'];
        $qtytotal = $qtytoko + $qty;
        if ($select) {
            $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids $idp cancel'");
            $num = mysqli_num_rows($cektransaksi);
            if ($num == 0) {
                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, id_history, nama_user) VALUES('$ids $idp cancel', '$qtytoko', '$qtytotal', 'order cancel', '$date', '$qty', '$idp', '$ids', '$user')");
                if ($insert) {
                    $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$qtytotal' WHERE id_toko = '$idp'");
                }
            } else {
                echo 'ada data yang masuk 2 kali';
            }
        }
    }
    echo '<script>
    alert("SKU TOKO ' . $sku . ' telah ditambahkan dari quantity ' . $qtytoko . ' menjadi ' . $qtytotal . '");
    window.location.href="?url=cancel&noresi=' . $inv . '";
    </script>';
}

if (isset($_POST['cancelgudang'])) {
    $ids = $_POST['ids'];
    $qty = $_POST['jumlah'];
    $idp = $_POST['idp'];
    $user = $_POST['user'];
    $inv = $_POST['inv'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids'");
    if ($update) {
        $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$idp'");
        $idProduct = mysqli_fetch_array($select)['id_product'];
        if ($select) {
            $selectkomp = mysqli_query($conn, "SELECT id_komponen, quantity_komponen FROM list_komponen WHERE id_product_finish = '$idProduct'");
            while ($data = mysqli_fetch_array($selectkomp)) {
                $idKomponen = $data['id_komponen'];
                $qtykomp = $data['quantity_komponen'];
                $qtytotal = $qty * $qtykomp;
                $insert = mysqli_query($conn, "INSERT INTO request_gudang (id_product, id_gudang, id_history, status, jenis, date, quantity_req) VALUES ('$idProduct', '$idKomponen', '$ids', 'requested', 'cancel', '$date', '$qtytotal')");
            }
        }
    }
}

// add deadline kurir
if (isset($_POST['adddeadline'])) {
    $namakurir = $_POST['namakurir'];
    $date = $_POST['waktu'];
    foreach ($namakurir as $kurir) {
        $insert = mysqli_query($conn, "INSERT INTO schedule_id(kurir, deadline) VALUES ('$kurir', '$date')");
        if ($insert) {
            header('location:?url=schedule');
        }
    }
}

// ubah deadline kurir
if (isset($_POST['ubahdeadline'])) {
    $namakurir = $_POST['namakurir'];
    $date = $_POST['waktu'];
    $ids = $_POST['ids'];
    foreach ($namakurir as $kurir) {
        $update = mysqli_query($conn, "UPDATE schedule_id SET kurir = '$kurir', deadline = '$date' WHERE id_schedule = '$ids'");
    }
}

if (isset($_POST['hapusdeadline'])) {
    $namakurir = $_POST['namakurir'];
    $date = $_POST['waktu'];
    $ids = $_POST['ids'];
    foreach ($namakurir as $kurir) {
        $update = mysqli_query($conn, "DELETE FROM schedule_id WHERE id_schedule='$ids'");
    }
}

if (isset($_POST['reqbapa'])) {
    $skut = $_POST['skut'];
    $qtyArray = $_POST['qty'];
    $noteArray = $_POST['note'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $user = $_POST['user'];

    foreach ($skut as $key => $value) {
        if ($value != '0') {
            $select = mysqli_query($conn, "SELECT id_toko, quantity_toko FROM toko_id WHERE sku_toko='$value'");
            $data = mysqli_fetch_array($select);
            if ($data) {
                $id_toko = $data['id_toko'];
                $quantity_toko = $data['quantity_toko'];
                $quantity_request = $qtyArray[$key];
                $note = $noteArray[$key];
                $insert = mysqli_query($conn, "INSERT INTO request_toko(id_product, quantity_request, note, status_request) VALUES('$id_toko','$quantity_request','$note','Approved')");
                if ($insert) {
                    $kurang = $quantity_toko - $quantity_request;
                    $update = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES('req $id_toko $date Req toko','$quantity_toko','$kurang','Req toko','-{$quantity_request}','$id_toko','0','$note', '$user')");
                    if ($update) {
                        $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$kurang' WHERE id_toko='$id_toko'");
                        if (!$updatetoko) {
                            echo 'data gagal masuk';
                        }
                    } else {
                    }
                } else {
                }
            } else {
            }
        }
    }
}

//Adjustment Button
if (isset($_POST['adjustmentButton'])) {
    $sku_toko = $_POST['sku_toko'];
    $quantity = $_POST['quantity'];
    $note = $_POST['note'];
    $user = $_POST['user'];

    $selecttoko = mysqli_query($conn, "SELECT id_toko, quantity_toko FROM toko_id WHERE sku_toko='$sku_toko'");
    $data = mysqli_fetch_array($selecttoko);
    $id_toko = $data['id_toko'];
    $quantitytoko = $data['quantity_toko'];

    if ($quantitytoko > $quantity) {
        $kurang = $quantity - $quantitytoko;
        date_default_timezone_set('Asia/Jakarta');
        $date = date('Y-m-d H:i:s');
        $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='adj $id_toko $date'");
        $num = mysqli_num_rows($cektransaksi);

        if ($num == 0) {
            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, note_transaksi,nama_user) VALUES('adj $id_toko $date','$quantitytoko','$quantity','adjustment','$kurang','$id_toko','0','$note', '$user')");
            if ($insert) {
                $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$quantity' WHERE id_toko='$id_toko'");
                header('location:?url=alltransaksi');
            }
        } else {
            echo 'ada data yang sama masuk 2 kali';
        }
    } else {
        $tambah = $quantity - $quantitytoko;
        date_default_timezone_set('Asia/Jakarta');
        $date = date('Y-m-d H:i:s');
        $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='adj $id_toko $date'");
        $num = mysqli_num_rows($cektransaksi);

        if ($num == 0) {
            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES('adj $id_toko $date','$quantitytoko','$quantity','adjustment','$tambah','$id_toko','0','$note', '$user')");
            if ($insert) {
                $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$quantity' WHERE id_toko='$id_toko'");
                header('location:?url=alltransaksi');
            }
        } else {
            echo 'ada data yang sama masuk 2 kali';
        }
    }
    $allowed_extensions = array('png', 'jpg', 'jpeg', 'svg', 'webp');
    $file_tmp = $_FILES['file']['tmp_name'];
    $namaimage = $_FILES['file']['name'];
    date_default_timezone_set('Asia/Jakarta');
    $current_date = date('Y-m-d-H-i');
    $dot = explode('.', $namaimage);
    $ekstensi = strtolower(end($dot));
    $image = $sku_toko . '-' . $current_date . '.' . $ekstensi; //compile
    $destination = '../assets/img_adjustment/' . $image;

    if (in_array($ekstensi, $allowed_extensions)) {
        if (move_uploaded_file($file_tmp, $destination)) {
            compressImage($destination, $destination, 25);
        } else {
            echo "Failed to move the file.";
        }
    } else {
        echo "Invalid file extension.";
    }
}

if (isset($_POST['refillall'])) {
    $id_toko = $_POST['idtoko'];
    $tipe = $_POST['tipe'];
    $stat = $_POST['stat'];
    $hari_ini = date('Y-m-d');
    $requester = $_POST['requester'];
    $count = count($id_toko);
    $date = date('Y-m-d H:i:s');
    $date2 = date('Y-m-d');
    for ($i = 0; $i < $count; $i++) {
        $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq, id_toko, type_req, requester, status_req, status_toko, date, tipe_pesanan) VALUES('$id_toko[$i] $date demand','$id_toko[$i]', '$tipe', '$requester', '$stat', 'on process toko', '$date', 'Demand')");
        $idRequest = mysqli_insert_id($conn);
        if ($insert) {
            $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'On Process' WHERE id_toko = '$id_toko[$i]' AND status = 'unprocessed'");
            if ($updatestatusdemand) {
                $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$id_toko[$i]'");
                $assoc = mysqli_fetch_array($select);
                $idp = $assoc['id_product'];
                if ($select) {
                    $select3 = mysqli_query($conn, "
                            SELECT id_gudang, stock_opname, id_product FROM list_komponen
                            INNER JOIN mateng_id ON mateng_id.id_product = list_komponen.id_komponen
                            WHERE list_komponen.id_product_finish = '$idp'
                            AND (stock_opname IS NULL OR stock_opname <= '$date2')
                            UNION ALL
                            SELECT id_gudang, stock_opname, id_product FROM list_komponen
                            INNER JOIN gudang_id ON gudang_id.id_product = list_komponen.id_komponen
                            WHERE list_komponen.id_product_finish = '$idp'
                            AND (stock_opname IS NULL OR stock_opname <= '$date2')
                            ");
                    while ($data3 = mysqli_fetch_array($select3)) {
                        $id_gudang = $data3['id_gudang'];
                        $id_product = $data3['id_product'];
                        $dateso = $data3['stock_opname'];
                        $select4 = mysqli_query($conn, "SELECT id_gudang FROM so_id WHERE id_gudang = '$id_gudang' AND result = ''");
                        if (mysqli_num_rows($select4) == 0) {
                            $insert2 = mysqli_query($conn, "INSERT INTO so_id (id_gudang, jenis, id_refrence, result, id_product, date)
                                VALUES ('$id_gudang', 'toko', '$idRequest', '', '$id_product', '$date')
                                ");
                        }
                    }
                }
            }
        }
    }
    header('location:?url=demand');
}


if (isset($_POST['tambah_barang'])) {
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $penerima = $_POST['penerima'];
    $kurir = $_POST['kurir'];
    $olshop = $_POST['olshop'];
    $tanggal_pengiriman = $_POST['tanggal_pengiriman'];
    $tanggal_bayar = $_POST['tanggal_bayar'];

    $select = mysqli_query($conn, "SELECT toko_id.id_toko,nama FROM toko_id,product_toko_id WHERE sku_toko = '$sku' AND toko_id.id_product = product_toko_id.id_product");
    $hitung = mysqli_num_rows($select);

    if ($hitung == 0) {
        echo '<script>
        alert("SKU tidak ditemukan");
        window.location.href="?url=tambahbarang&noresi=' . $inv . '";
        </script>';
    } else {
        $data = mysqli_fetch_assoc($select);
        $nama = $data['nama'];
        $idp = $data['id_toko'];
        $insert = mysqli_query($conn, "INSERT INTO shop_id(id_orderitem, status_order, invoice,tanggal_bayar,nama_product,sku_toko,jumlah,penerima,kurir,resi,tanggal_pengiriman,id_product,olshop) VALUES('$inv $sku add','additional','$inv','$tanggal_bayar','$nama','$sku','$jumlah','$penerima','$kurir','$resi','$tanggal_pengiriman','$idp','$olshop')");
        if ($insert) {
            echo '<script>
        alert("Berhasil menambahkan barang");
        window.location.href="?url=tambahbarang&noresi=' . $inv . '";
        </script>';
        }
    }
}

if (isset($_POST['editbaranginvoice'])) {
    $ids = $_POST['ids'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];
    $note = $_POST['note'];
    $jumlah1 = $_POST['jumlah1'];
    $sku1 = $_POST['sku1'];

    $select = mysqli_query($conn, "SELECT nama_product,sku_toko,id_product,jumlah FROM shop_id WHERE id_shop = '$ids'");
    $data = mysqli_fetch_assoc($select);
    $namalama = $data['nama_product'];
    $skulama = $data['sku_toko'];
    $idplama = $data['id_product'];
    $jumlahlama = $data['jumlah'];
    if ($data) {
        $ambil = mysqli_query($conn, "SELECT nama,toko_id.id_toko FROM toko_id,product_toko_id WHERE sku_toko = '$sku' AND product_toko_id.id_product = toko_id.id_product");
        $list = mysqli_fetch_assoc($ambil);
        $namabaru = $list['nama'];
        $idpbaru = $list['id_toko'];
        if ($sku != $sku1) {
            $insert = mysqli_query($conn, "INSERT INTO refund_id (id_shop,invoice,resi,nama_product,sku_awal,jumlah_awal,id_product_awal,nama_product_ganti,sku_ganti,jumlah_ganti,id_product_ganti,status) VALUES ('$ids','$inv','$resi','$namalama','$skulama','$jumlahlama','$idplama','$namabaru','$sku','$jumlah','$idpbaru','Changed')");
            if ($insert) {
                $update = mysqli_query($conn, "UPDATE shop_id SET nama_product = '$namabaru' , sku_toko = '$sku', jumlah = '$jumlah' , id_product = '$idpbaru', status_order = 'Changed',note = '$note' WHERE id_shop = '$ids'");
                if ($update) {
                    $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Changed' WHERE invoice = '$inv'");
                    header('location:?url=tambahbarang&noresi=' . $inv . '');
                }
            }
        } elseif ($jumlah != $jumlah1) {
            $insert = mysqli_query($conn, "INSERT INTO refund_id (id_shop,invoice,resi,nama_product,sku_awal,jumlah_awal,id_product_awal,nama_product_ganti,sku_ganti,jumlah_ganti,id_product_ganti,status) VALUES ('$ids','$inv','$resi','$namalama','$skulama','$jumlahlama','$idplama','$namabaru','$sku','$jumlah','$idpbaru','Parsial Refund')");
            if ($insert) {
                $update = mysqli_query($conn, "UPDATE shop_id SET jumlah = '$jumlah', status_order = 'Parsial Refund', note = '$note' WHERE id_shop = '$ids'");
                if ($update) {
                    $track = mysqli_query($conn, "UPDATE tracking SET refaund = 'Changed' WHERE invoice = '$inv'");
                    header('location:?url=tambahbarang&noresi=' . $inv . '');
                }
            }
        } else {
            $update = mysqli_query($conn, "UPDATE shop_id SET note = '$note' WHERE id_shop = '$ids'");
            if ($update) {
                header('location:?url=tambahbarang&noresi=' . $inv . '');
            }
        }
    }
}

if (isset($_POST['approvetoko'])) {
    $qty = $_POST['qty'];
    $idr = $_POST['idr'];
    $user = $_POST['user'];
    $berhasil = 0;
    $gagal = 0;
    $date = date('Y-m-d H:i:s');
    foreach ($idr as $i) {
        $selectlist = mysqli_query($conn, "SELECT quantity_req, id_toko, type_req, user_so FROM request_id WHERE id_request='$i'");

        // Fetch the request data
        if ($datalist = mysqli_fetch_array($selectlist)) {
            $qtyreq = $datalist['quantity_req'];
            $id_toko = $datalist['id_toko'];
            $type_req = $datalist['type_req'];
            $user_so = $datalist['user_so'];

            // Fetch toko data
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko='$id_toko'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantity_toko = $datatoko['quantity_toko'];

            // Update request
            $updateRequest = mysqli_query($conn, "UPDATE request_id SET count_toko = '{$qty[$i]}', user_so = '$user', date_so = '$date' WHERE id_request = '$i'");

            if ($updateRequest) {
                $select = mysqli_query($conn, "SELECT count_toko FROM request_id WHERE id_request = '$i'");
                $dataqty = mysqli_fetch_array($select);
                $qtytoko = $dataqty['count_toko'];

                if ($quantity_toko == $qtytoko) {
                    // Approve if quantities match
                    $update = mysqli_query($conn, "UPDATE request_id SET status_toko = 'Approved', user_so = '$user', date_so = '$date' WHERE id_request = '$i' AND status_toko != 'Approved'");
                    if ($update) {
                        mysqli_query($conn, "UPDATE toko_id SET flagging = NULL, date_so = NULL WHERE id_toko = '$id_toko'");
                        $berhasil++;
                    }
                } else {
                    // Failcheck if quantities do not match
                    mysqli_query($conn, "UPDATE request_id SET status_toko = 'Failcheck', user_so = '$user', date_so = '$date' WHERE id_request = '$i' AND status_toko != 'Approved'");
                    $gagal++;
                }
            }
        } else {
            echo "Error fetching data for id_request $i: " . mysqli_error($conn);
        }
    }

    echo "<script>alert('$berhasil so berhasil & $gagal gagal');</script>";
}


if (isset($_POST['adjustmentso'])) {
    $idr = $_POST['idr'];
    $quantity = $_POST['quantityakhir'];
    $note = $_POST['note'];
    $user = $_POST['user'];

    $count = count($idr);
    for ($i = 0; $i < $count; $i++) {
        $selectdata = mysqli_query($conn, "SELECT id_request, id_toko, count_toko FROM request_id WHERE id_request='$idr[$i]'");
        $data = mysqli_fetch_array($selectdata);
        $id_request = $data['id_request'];
        $id_toko = $data['id_toko'];
        $count_toko = $data['count_toko'];
        if ($selectdata) {
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, sku_toko FROM toko_id WHERE id_toko='$id_toko'");
            $datatoko = mysqli_fetch_array($selecttoko);
            $quantitytoko = $datatoko['quantity_toko'];
            $sku_toko = $datatoko['sku_toko'];

            $allowed_extensions = array('png', 'jpg', 'jpeg', 'svg', 'webp');
            $file_tmp = $_FILES['file']['tmp_name'];
            $namaimage = $_FILES['file']['name'];
            $file_error = $_FILES['file']['error'];
            $file_size = $_FILES['file']['size'];

            date_default_timezone_set('Asia/Jakarta');
            $current_date = date('Y-m-d-H-i');
            $dot = explode('.', $namaimage[$i]);
            $ekstensi = strtolower(end($dot));
            $image = $sku_toko . '-' . $current_date . '.' . $ekstensi; // compile
            $destination = '../assets/img_adjustment/' . $image;

            if (in_array($ekstensi, $allowed_extensions)) {
                if ($file_error[$i] === UPLOAD_ERR_OK) {
                    // Check if destination directory is writable
                    if (is_writable(dirname($destination))) {
                        if (move_uploaded_file($file_tmp[$i], $destination)) {
                            if ($quantitytoko > $quantity[$i]) {
                                $kurang = $quantity[$i] - $quantitytoko;
                                date_default_timezone_set('Asia/Jakarta');
                                $date = date('Y-m-d H:i:s');
                                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='adj $id_toko $date $id_request'");
                                $num = mysqli_num_rows($cektransaksi);

                                if ($num == 0) {
                                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES('adj $id_toko $date $id_request','$quantitytoko','$quantity[$i]','adjustment so','$kurang','$id_toko','$id_request','$note[$i]', '$user')");
                                    if ($insert) {
                                        $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$quantity[$i]' WHERE id_toko='$id_toko'");
                                        if ($update) {
                                            $updaterequest = mysqli_query($conn, "UPDATE request_id SET status_toko = 'on process toko' WHERE id_request = '$id_request' AND type_req= 'refill'");
                                            header('location:?url=alltransaksi');
                                        }
                                    }
                                } else {
                                    echo 'ada data yang sama masuk 2 kali';
                                }
                            } else {
                                $tambah = $quantity[$i] - $quantitytoko;
                                date_default_timezone_set('Asia/Jakarta');
                                $date = date('Y-m-d H:i:s');
                                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='adj $id_toko $date $id_request'");
                                $num = mysqli_num_rows($cektransaksi);

                                if ($num == 0) {
                                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES('adj $id_toko $date $id_request','$quantitytoko','$quantity[$i]','adjustment so','$tambah','$id_toko','$id_request','$note[$i]', '$user')");
                                    if ($insert) {
                                        $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$quantity[$i]' WHERE id_toko='$id_toko'");
                                        if ($update) {
                                            $updaterequest = mysqli_query($conn, "UPDATE request_id SET status_toko = 'on process toko' WHERE id_request = '$id_request' AND type_req= 'refill'");
                                            header('location:?url=alltransaksi');
                                        }
                                    }
                                } else {
                                    echo 'ada data yang sama masuk 2 kali';
                                }
                            }
                        } else {
                            echo "Failed to move the file to the destination.";
                            echo "Temporary file: " . $file_tmp[$i] . "<br>";
                            echo "Destination: " . $destination . "<br>";
                            echo "Is writable: " . (is_writable(dirname($destination)) ? "Yes" : "No") . "<br>";
                        }
                    } else {
                        echo "Destination directory is not writable.";
                    }
                } else {
                    echo "File upload error: " . $file_error[$i];
                }
            } else {
                echo "Invalid file extension.";
            }
        }
    }
}

if (isset($_POST['picking'])) {
    foreach ($_POST['picking'] as $id_shop => $value) {
        $option = isset($_POST['option'][$id_shop]) ? $_POST['option'][$id_shop] : '';
        $insert = mysqli_query($conn, "UPDATE checking_id SET fail = '$option', status = '' WHERE id_shop = '$id_shop'");
        if ($insert) {
            echo "
            <script>
            alert('id shop $id_shop telah ditandai sebagai salah $option');
            </script>
            ";
        }
    }
}

if (isset($_POST['dataload'])) {
    $idp = $_POST['idtoko'];
    $tipe = $_POST['tipe'];
    $stat = $_POST['stat'];
    $qty = $_POST['qty'];
    $inv = $_POST['inv'];
    $ids = $_POST['ids'];
    $requester = $_POST['requester'];
    $date = date('Y-m-d H:i:s');
    $date2 = date('Y-m-d');
    $date3 = date('Y-m-d H');
    $user = $_POST['requester'];
    foreach ($idp as $key => $id) {
        if ($tipe[$id] == 'Request') {
            $select = mysqli_query($conn, "SELECT kurir FROM shop_id WHERE id_shop = '$ids[$id]'");
            $data = mysqli_fetch_assoc($select);
            $kurir = $data['kurir'];
            if (stripos($kurir, "Instant") !== false || stripos($kurir, "Same Day") !== false || stripos($kurir, "SameDay") !== false || stripos($kurir, "cargo") !== false) {
                $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' WHERE id_shop = '$ids[$id]'");
                if ($insert) {
                    $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$id $inv[$id]'");
                    $hitung = mysqli_num_rows($cek);
                    if ($hitung == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO request_id (uniq_idreq, id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester, status_req) VALUES ('$id $inv[$id]', '$id', '$inv[$id]', '$qty[$id]', 'Request', 'Instant', '$user[$id]', 'unprocessed')");
                        if ($insert) {
                            $selectIdReq = mysqli_query($conn, "SELECT id_request FROM request_id WHERE type_req = '$tipe[$id]' AND id_toko = '$id' AND status_req = 'unprocessed' ORDER BY date DESC LIMIT 1");
                            $fetchIdReq = mysqli_fetch_array($selectIdReq);
                            $idRequest = $fetchIdReq['id_request'];
                            if ($selectIdReq) {
                                $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$id'");
                                $assoc = mysqli_fetch_array($select);
                                $idp = $assoc['id_product'];
                                if ($select) {
                                    $select3 = mysqli_query($conn, "
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN mateng_id ON mateng_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                        UNION ALL
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN gudang_id ON gudang_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                    ");
                                    while ($data3 = mysqli_fetch_array($select3)) {
                                        $id_gudang = $data3['id_gudang'];
                                        $id_product = $data3['id_product'];
                                        $dateso = $data3['stock_opname'];
                                        $select4 = mysqli_query($conn, "SELECT id_gudang FROM so_id WHERE id_gudang = '$id_gudang' AND result = ''");
                                        if (mysqli_num_rows($select4) == 0) {
                                            $insert2 = mysqli_query($conn, "INSERT INTO so_id (id_gudang, jenis, id_refrence, result, id_product, date)
                                                VALUES ('$id_gudang', 'toko', '$idRequest', '', '$id_product', '$date')");
                                        }
                                    }
                                }
                            }
                        } else {
                            error_log("Failed to get last insert id for request_id: " . mysqli_error($conn));
                        }
                    }
                } else {
                    error_log("Failed to update shop_id: " . mysqli_error($conn));
                }
            } elseif (stripos($kurir, 'JNE') !== false) {
                $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' WHERE id_shop = '$ids[$id]'");
                if ($insert) {
                    $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$id $inv[$id]'");
                    $hitung = mysqli_num_rows($cek);
                    if ($hitung == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO request_id (uniq_idreq, id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester, status_req) VALUES ('$id $inv[$id]', '$id', '$inv[$id]', '$qty[$id]', 'Request', 'Reguler (JNE)', '$user[$id]', 'unprocessed')");
                        if ($insert) {
                            $selectIdReq = mysqli_query($conn, "SELECT id_request FROM request_id WHERE type_req = '$tipe[$id]' AND id_toko = '$id' AND status_req = 'unprocessed' ORDER BY date DESC LIMIT 1");
                            $fetchIdReq = mysqli_fetch_array($selectIdReq);
                            $idRequest = $fetchIdReq['id_request'];
                            if ($selectIdReq) {
                                $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$id'");
                                $assoc = mysqli_fetch_array($select);
                                $idp = $assoc['id_product'];
                                if ($select) {
                                    $select3 = mysqli_query($conn, "
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN mateng_id ON mateng_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                        UNION ALL
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN gudang_id ON gudang_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                    ");
                                    while ($data3 = mysqli_fetch_array($select3)) {
                                        $id_gudang = $data3['id_gudang'];
                                        $id_product = $data3['id_product'];
                                        $dateso = $data3['stock_opname'];
                                        $select4 = mysqli_query($conn, "SELECT id_gudang FROM so_id WHERE id_gudang = '$id_gudang' AND result = ''");
                                        if (mysqli_num_rows($select4) == 0) {
                                            $insert2 = mysqli_query($conn, "INSERT INTO so_id (id_gudang, jenis, id_refrence, result, id_product, date)
                                                VALUES ('$id_gudang', 'toko', '$idRequest', '', '$id_product', '$date')");
                                        }
                                    }
                                }
                            }
                        } else {
                            error_log("Failed to get last insert id for request_id: " . mysqli_error($conn));
                        }
                    }
                } else {
                    error_log("Failed to update shop_id: " . mysqli_error($conn));
                }
            } else {
                $insert = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' WHERE id_shop = '$ids[$id]'");
                if ($insert) {
                    $cek = mysqli_query($conn, "SELECT uniq_idreq FROM request_id WHERE uniq_idreq = '$id $inv[$id]'");
                    $hitung = mysqli_num_rows($cek);
                    if ($hitung == 0) {
                        $insert = mysqli_query($conn, "INSERT INTO request_id (uniq_idreq, id_toko, invoice, quantity_req, type_req, tipe_pesanan, requester, status_req) VALUES ('$id $inv[$id]', '$id', '$inv[$id]', '$qty[$id]', 'Request', 'Reguler', '$user[$id]', 'unprocessed')");
                        if ($insert) {
                            $selectIdReq = mysqli_query($conn, "SELECT id_request FROM request_id WHERE type_req = '$tipe[$id]' AND id_toko = '$id' AND status_req = 'unprocessed' ORDER BY date DESC LIMIT 1");
                            $fetchIdReq = mysqli_fetch_array($selectIdReq);
                            $idRequest = $fetchIdReq['id_request'];
                            if ($selectIdReq) {
                                $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$id'");
                                $assoc = mysqli_fetch_array($select);
                                $idp = $assoc['id_product'];
                                if ($select) {
                                    $select3 = mysqli_query($conn, "
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN mateng_id ON mateng_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                        UNION ALL
                                        SELECT id_gudang, stock_opname, id_product FROM list_komponen
                                        INNER JOIN gudang_id ON gudang_id.id_product = list_komponen.id_komponen
                                        WHERE list_komponen.id_product_finish = '$idp'
                                        AND (stock_opname IS NULL OR stock_opname <= '$date2')
                                    ");
                                    while ($data3 = mysqli_fetch_array($select3)) {
                                        $id_gudang = $data3['id_gudang'];
                                        $id_product = $data3['id_product'];
                                        $dateso = $data3['stock_opname'];
                                        $select4 = mysqli_query($conn, "SELECT id_gudang FROM so_id WHERE id_gudang = '$id_gudang' AND result = '' AND jenis != 'pack'");
                                        if (mysqli_num_rows($select4) == 0) {
                                            $insert2 = mysqli_query($conn, "INSERT INTO so_id (id_gudang, jenis, id_refrence, result, id_product, date)
                                                VALUES ('$id_gudang', 'toko', '$idRequest', '', '$id_product', '$date')");
                                        }
                                    }
                                }
                            } else {
                                error_log("Failed to get last insert id for request_id: " . mysqli_error($conn));
                            }
                        }
                    }
                } else {
                    error_log("Failed to update shop_id: " . mysqli_error($conn));
                }
            }
        } elseif ($tipe[$id] == 'refill') {
            $insert = mysqli_query($conn, "INSERT INTO request_id (uniq_idreq, id_toko, type_req, requester, status_req, status_toko, tipe_pesanan, quantity_req) VALUES ('$id $date3', '$id', '$tipe[$id]', '$requester[$id]', 'unprocessed', 'on process toko', 'Summary','$qty[$id]')");
            if ($insert) {
                $updatestatusdemand = mysqli_query($conn, "UPDATE demand_toko SET status = 'On Process' WHERE id_toko = '$id' AND status = 'unprocessed'");
                if ($updatestatusdemand) {
                    $selectIdReq = mysqli_query($conn, "SELECT id_request FROM request_id WHERE type_req = '$tipe[$id]' AND id_toko = '$id' AND status_req = 'unprocessed' ORDER BY date DESC LIMIT 1");
                    $fetchIdReq = mysqli_fetch_array($selectIdReq);
                    $idRequest = $fetchIdReq['id_request'];
                    if ($selectIdReq) {
                        $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$id'");
                        $assoc = mysqli_fetch_array($select);
                        $idp = $assoc['id_product'];
                        if ($select) {
                            $select3 = mysqli_query($conn, "
                            SELECT id_gudang, stock_opname, id_product FROM list_komponen
                            INNER JOIN mateng_id ON mateng_id.id_product = list_komponen.id_komponen
                            WHERE list_komponen.id_product_finish = '$idp'
                            AND (stock_opname IS NULL OR stock_opname <= '$date2')
                            UNION ALL
                            SELECT id_gudang, stock_opname, id_product FROM list_komponen
                            INNER JOIN gudang_id ON gudang_id.id_product = list_komponen.id_komponen
                            WHERE list_komponen.id_product_finish = '$idp'
                            AND (stock_opname IS NULL OR stock_opname <= '$date2')
                        ");
                            while ($data3 = mysqli_fetch_array($select3)) {
                                $id_gudang = $data3['id_gudang'];
                                $id_product = $data3['id_product'];
                                $dateso = $data3['stock_opname'];
                                $select4 = mysqli_query($conn, "SELECT id_gudang FROM so_id WHERE id_gudang = '$id_gudang' AND result = '' AND jenis != 'pack'");
                                if (mysqli_num_rows($select4) == 0) {
                                    $insert2 = mysqli_query($conn, "INSERT INTO so_id (id_gudang, jenis, id_refrence, result, id_product, date)
                                    VALUES ('$id_gudang', 'toko', '$idRequest', '', '$id_product', '$date')");
                                }
                            }
                        }
                    }
                } else {
                    error_log("Failed to get last insert id for request_id: " . mysqli_error($conn));
                }
            } else {
                error_log("Failed to insert into request_id: " . mysqli_error($conn));
            }
        }
    }
    header('location:?url=request');
}

if (isset($_POST['retur'])) {
    $ids = $_POST['ids'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];
    $output = $_POST['note'];
    $date = date('Y-m-d H:i:s');
    $uniq = $inv . $date . $sku;
    $jenis = "retur";

    // Use parameterized query to prevent SQL injection
    $select = mysqli_prepare($conn, "SELECT jumlah,id_product FROM shop_id WHERE id_shop = ?");
    mysqli_stmt_bind_param($select, "s", $ids);
    mysqli_stmt_execute($select);
    $result = mysqli_stmt_get_result($select);
    $data = mysqli_fetch_assoc($result);
    $jumlahlama = $data['jumlah'];
    $id_toko = $data['id_product'];
    $hasil = $jumlahlama - $jumlah;

    if ($data) {
        if ($output == 'toko') {
            $ambil = mysqli_prepare($conn, "SELECT quantity_toko, nama, toko_id.id_toko FROM toko_id, product_toko_id WHERE sku_toko = ? AND product_toko_id.id_product = toko_id.id_product");
            mysqli_stmt_bind_param($ambil, "s", $sku);
            mysqli_stmt_execute($ambil);
            $result = mysqli_stmt_get_result($ambil);
            $list = mysqli_fetch_assoc($result);
            $namabaru = $list['nama'];
            $quantity = $list['quantity_toko'];
            $idtbaru = $list['id_toko'];
            $tambah = $quantity + $jumlah;
            if ($list) {
                $uniq_transaksi = $ids . $sku . $date;
                $cektransaksi = mysqli_prepare($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi = ?");
                mysqli_stmt_bind_param($cektransaksi, "s", $uniq_transaksi);
                mysqli_stmt_execute($cektransaksi);
                $result = mysqli_stmt_get_result($cektransaksi);
                $num = mysqli_num_rows($result);
                if ($num == 0) {
                    $jenis = "retur";
                    $insert = mysqli_prepare($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES(?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($insert, "siisiii", $uniq_transaksi, $quantity, $tambah, $jenis, $jumlah, $idtbaru, $ids);
                    mysqli_stmt_execute($insert);
                    if ($insert) {
                        $updatetoko = mysqli_prepare($conn, "UPDATE toko_id SET quantity_toko = ? WHERE id_toko = ?");
                        mysqli_stmt_bind_param($updatetoko, "ii", $tambah, $idtbaru);
                        mysqli_stmt_execute($updatetoko);
                        if ($updatetoko) {
                            $select = mysqli_prepare($conn, "SELECT quantity FROM retur_id WHERE id_shop = ?");
                            mysqli_stmt_bind_param($select, "i", $ids);
                            mysqli_stmt_execute($select);
                            $result = mysqli_stmt_get_result($select);
                            $datas = mysqli_fetch_assoc($result);
                            $jum1 = $datas['quantity'];
                            $jumqty = $jum1 + $jumlah;
                            $update = mysqli_prepare($conn, "UPDATE retur_id SET quantity = ? WHERE id_shop = ?");
                            mysqli_stmt_bind_param($update, "ii", $jumqty, $ids);
                            mysqli_stmt_execute($update);
                            if ($update) {
                                $noteupdate = "retur ($jumqty)";
                                $update = mysqli_prepare($conn, "UPDATE shop_id SET nama_product = ?, sku_toko = ?, jumlah = ?, id_product = ?, note = ? WHERE id_shop = ?");
                                mysqli_stmt_bind_param($update, "ssiisi", $namabaru, $sku, $hasil, $idtbaru, $noteupdate, $ids);
                                mysqli_stmt_execute($update);
                                if ($update) {
                                    $track = mysqli_prepare($conn, "UPDATE tracking SET refaund = 'retur' WHERE invoice = ?");
                                    mysqli_stmt_bind_param($track, "s", $inv);
                                    mysqli_stmt_execute($track);
                                    header('location:?url=retur&noresi=' . $inv . '');
                                }
                            }
                        }
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
            }
        } else if ($output == 'gudang') {
            $select = mysqli_prepare($conn, "SELECT id_request FROM request_id WHERE invoice = ? AND id_toko = ?");
            mysqli_stmt_bind_param($select, "ss", $inv, $id_toko);
            mysqli_stmt_execute($select);
            $datalist = mysqli_stmt_get_result($select);
            $data = mysqli_fetch_assoc($datalist);
            $idr = $data['id_request'];
            if ($data) {
                $reqtotal = mysqli_prepare($conn, "SELECT id_gudang FROM request_total WHERE id_request = ?");
                mysqli_stmt_bind_param($reqtotal, "i", $idr);
                mysqli_stmt_execute($reqtotal);
                $list = mysqli_stmt_get_result($reqtotal);
                while ($list = mysqli_fetch_assoc($list)) {
                    $idg = $list['id_gudang'];
                    $table = ($idg < 1000000) ? 'mateng_id' : 'gudang_id';
                    $select = mysqli_prepare($conn, "SELECT quantity,id_product FROM $table WHERE id_gudang = ?");
                    mysqli_stmt_bind_param($select, "i", $idg);
                    mysqli_stmt_execute($select);
                    $data = mysqli_stmt_get_result($select);
                    $data = mysqli_fetch_assoc($data);
                    $qtygudang = $data['quantity'];
                    $idp = $data['id_product'];
                    $querykomp = mysqli_query($conn, "SELECT quantity_komponen FROM list_komponen WHERE id_product_finish = '$id_toko' AND id_komponen = '$idp'");
                    $datakomp = mysqli_fetch_assoc($querykomp);
                    $qtykomp = $datakomp['quantity_komponen'];
                    $jumlahkomp = $qtykomp * $jumlah;
                    $tambah = $qtygudang + $jumlahkomp;
                    $cek = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_gudang WHERE uniq_transaksi = '$uniq'");
                    $hitung = mysqli_num_rows($cek);
                    if ($hitung == 0) {
                        // history transaksi
                        $history = mysqli_prepare($conn, "INSERT INTO transaksi_gudang(uniq_transaksi,stok_sebelum,stok_sesudah,jenis_transaksi,jumlah,id_gudang,id_pengurang) VALUES (?,?,?,?,?,?,?) ");
                        mysqli_stmt_bind_param($history, "siisiii", $uniq, $qtygudang, $tambah, $jenis, $jumlahkomp, $idg, $ids);
                        mysqli_stmt_execute($history);
                        if ($history) {
                            $query = mysqli_prepare($conn, "UPDATE $table SET quantity = ? WHERE id_gudang = ?");
                            mysqli_stmt_bind_param($query, "ii", $tambah, $idg);
                            mysqli_stmt_execute($query);
                            if ($query) {
                                $select = mysqli_prepare($conn, "SELECT quantity FROM retur_id WHERE id_shop = ?");
                                mysqli_stmt_bind_param($select, "i", $ids);
                                mysqli_stmt_execute($select);
                                $result = mysqli_stmt_get_result($select);
                                $datas = mysqli_fetch_assoc($result);
                                $jum1 = $datas['quantity'];
                                $jumqty = $jum1 + $jumlah;
                                $update = mysqli_prepare($conn, "UPDATE retur_id SET quantity = ? WHERE id_shop = ?");
                                mysqli_stmt_bind_param($update, "ii", $jumqty, $ids);
                                mysqli_stmt_execute($update);
                                if ($update) {
                                    $noteupdate = "retur ($jumqty)";
                                    $update = mysqli_prepare($conn, "UPDATE shop_id SET  jumlah = ?, note = ? WHERE id_shop = ?");
                                    mysqli_stmt_bind_param($update, "isi", $hasil, $noteupdate, $ids);
                                    mysqli_stmt_execute($update);
                                    if ($update) {
                                        $track = mysqli_prepare($conn, "UPDATE tracking SET refaund = 'retur' WHERE invoice = ?");
                                        mysqli_stmt_bind_param($track, "s", $inv);
                                        mysqli_stmt_execute($track);
                                        header('location:?url=retur&noresi=' . $inv . '');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

if (isset($_POST['refilldah'])) {
    $idp = $_POST['idtoko'];
    $tipe = $_POST['tipe'];
    $stat = $_POST['stat'];
    $qty = $_POST['qty'];
    $inv = $_POST['inv'];
    $requester = $_POST['requester'];
    $date = date('Y-m-d H:i:s');
    foreach ($idp as $key => $id) {
        if ($tipe[$id] == 'Request') {
            $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq, id_toko, invoice, quantity_req, type_req, requester, status_req) VALUES('$id $inv[$id]','$id', '$inv[$id]', '$qty[$id]', '$tipe[$id]', '$requester[$id]', '$stat[$id]')");
            if ($insert) {
                $update = mysqli_query($conn, "UPDATE shop_id SET status_pick = 'pending', output = 'gudang' WHERE invoice = '$inv[$id]' AND id_product = '$id'");
            } else {
                echo "Error inserting: " . mysqli_error($conn);
            }
        } elseif ($tipe[$id] == 'refill') {
            $insert = mysqli_query($conn, "INSERT INTO request_id(uniq_idreq, id_toko, type_req, requester, status_req, status_toko) VALUES('$id $date','$id', '$tipe[$id]', '$requester[$id]', '$stat[$id]', 'on process toko')");
        }
    }
}

if (isset($_POST['backPick'])) {
    $inv = $_POST['backPick'];

    $update = mysqli_query($conn, "UPDATE tracking SET picking = '' WHERE invoice = '$inv'");
    if ($update) {
        header('location:?url=invoicepend');
    }
}

if (isset($_POST['adjustmentchart'])) {
    $start_date = $_POST['start'];
    $end_date = $_POST['end'];

    $query = "SELECT 
    SUM(CASE WHEN jenis_transaksi = 'adjustment' THEN 1 ELSE 0 END) AS adjustment_count,
    SUM(CASE WHEN jenis_transaksi = 'adjustment so' THEN 1 ELSE 0 END) AS adjustment_so_count,
    DATE(date) AS transaction_date 
  FROM transaksi_toko 
  WHERE (jenis_transaksi = 'adjustment' OR jenis_transaksi = 'adjustment so')
  AND DATE(date) BETWEEN '$start_date' AND '$end_date'
  GROUP BY transaction_date";


    $select = mysqli_query($conn, $query);

    $dates = [];
    $adjustments = [];

    while ($data = mysqli_fetch_assoc($select)) {
        $dates[] = $data['transaction_date'];
        $adjustments[] = $data['adjustment_count'];
        $adjustmentsso[] = $data['adjustment_so_count'];
    }
}

if (isset($_POST['inputfoto'])) {
    date_default_timezone_set('Asia/Jakarta');
    $current_date = date('Y-m-d H:i:s');
    $date = date('Y-m-d');
    $btn = $_POST['inputfoto'];
    $id_toko = $_POST['idtokoJatuh'];
    $invoices = $_POST['invoiceJatuh'];
    $idHistory = $_POST['idshopJatuh'];
    $user = $_POST['user'];
    foreach ($btn as $index => $id) {
        $update = mysqli_query($conn, "UPDATE toko_id SET date_so = '$current_date', flagging = 'drop' WHERE id_toko = '$id_toko[$index]'");
        if ($update) {
            header('location:?url=resi&noresi=' . $invoices[$index] . '');
        }
    }
}

// Fungsi untuk mengompres gambar
function compressImage($source, $destination, $quality)
{
    $info = getimagesize($source);

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
    } elseif ($info['mime'] == 'image/webp') {
        $image = imagecreatefromwebp($source);
    } elseif ($info['mime'] == 'image/svg+xml') {
        // Handle SVG if needed
        return;
    } else {
        // Unsupported image type
        return;
    }

    imagejpeg($image, $destination, $quality);
    imagedestroy($image);
}


if (isset($_POST['rollback'])) {
    $ids = $_POST['ids'];
    $inv = $_POST['inv'];
    $resi = $_POST['resi'];
    $sku = $_POST['sku'];
    $jumlah = $_POST['jumlah'];

    $select = mysqli_query($conn, "SELECT output,status_pick,nama_product,sku_toko,id_product,jumlah FROM shop_id WHERE id_shop = '$ids'");
    $data = mysqli_fetch_assoc($select);
    $namalama = $data['nama_product'];
    $status = $data['status_pick'];
    $output = $data['output'];
    $skulama = $data['sku_toko'];
    $idplama = $data['id_product'];
    $jumlahlama = $data['jumlah'];

    if ($status == 'done' && $output == 'toko') {
        $update = mysqli_query($conn, "UPDATE shop_id SET output = '',status_pick = '' WHERE id_shop = '$ids'");
        if ($update) {
            $ambil = mysqli_query($conn, "SELECT quantity_toko,nama,toko_id.id_toko FROM toko_id,product_toko_id WHERE sku_toko = '$sku' AND product_toko_id.id_product = toko_id.id_product");
            $list = mysqli_fetch_assoc($ambil);
            $namabaru = $list['nama'];
            $qty = $list['quantity_toko'];
            $hasil = $qty + $selisih;
            $idpbaru = $list['id_toko'];
            if ($list) {
                $hasil = $qty + $jumlah;
                $cektransaksi = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi='$ids $idpbaru $jumlah'");
                $num = mysqli_num_rows($cektransaksi);

                if ($num == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history) VALUES('$ids $idpbaru $jumlah','$qty','$hasil','rollback','$jumlah','$idpbaru','$ids')");
                    if ($insert) {
                        $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko='$hasil' WHERE id_toko='$idpbaru'");
                    }
                } else {
                    echo 'ada data yang sama masuk 2 kali';
                }
                header('location:?url=detailpending&noresi=' . $inv . '');
            }
        }
    }
}

if (isset($_POST['rejectToko'])) {
    $skut = $_POST['skut'];
    $quantity = $_POST['quantity'];
    $user = $_POST['requester'];
    $kategori = $_POST['kategori'];
    $detail = $_POST['detail'];
    $lokasi = $_POST['lokasi'];
    $proses = $_POST['proses'];
    $jum = count($skut);
    $date = date('Y-m-d H:i:s');

    for ($i = 0; $i < $jum; $i++) {
        // Fetch `id_toko` and `id_product` from `toko_id` using the `sku_toko`
        $select = mysqli_query($conn, "SELECT id_toko, id_product FROM toko_id WHERE sku_toko = '$skut[$i]'");
        $data = mysqli_fetch_array($select);

        if ($data) {
            $idt = $data['id_toko'];
            $idp = $data['id_product'];

            // Insert into `reject_toko`
            $insertReject = mysqli_query($conn, "INSERT INTO reject_toko (id_toko, quantity, date, status, user_toko) 
                                                 VALUES ('$idt', '$quantity[$i]', '$date', 'not approve', '$user')");

            if ($insertReject) {
                // Fetch `id_komponen` from either `gudang_id` or `mateng_id`
                $query = mysqli_query($conn, "SELECT id_komponen FROM (
                                                SELECT id_komponen 
                                                FROM gudang_id 
                                                INNER JOIN list_komponen 
                                                ON list_komponen.id_komponen = gudang_id.id_product
                                                WHERE id_product_finish = '$idp'
                                                UNION ALL
                                                SELECT id_komponen 
                                                FROM mateng_id 
                                                INNER JOIN list_komponen 
                                                ON list_komponen.id_komponen = mateng_id.id_product
                                                WHERE id_product_finish = '$idp'
                                               ) AS combined");
                $fetch = mysqli_fetch_array($query);

                if ($fetch) {
                    $idkomp = $fetch['id_komponen'];

                    // Insert into `case_management`
                    $case = mysqli_query($conn, "INSERT INTO case_management (id_product, jenis_case, kategori, detail, tanggal, lokasi, proses, quantity) 
                                                 VALUES ('$idkomp', 'reject quality', '$kategori[$i]', '$detail[$i]', '$date', '$lokasi[$i]', '$proses[$i]', '$quantity[$i]')");
                }
            }
        }
    }
    header('Location: ?url=reject');
}

if (isset($_POST['restart'])) {
    $invoicess = $_POST['invoice'];
    $insert = mysqli_query($conn, "UPDATE checking_id SET status = '', quantity = '0' WHERE invoice = '$invoicess'");
}

if (isset($_POST['noteinput'])) {
    $invoice = $_POST['inv'];
    $note = $_POST['note'];
    $insert = mysqli_query($conn, "UPDATE tracking SET note_track = '$note' WHERE invoice = '$invoice'");
}

if (isset($_POST['deletemutasi'])) {
    $sku_lama = $_POST['sku_lama'];
    $idt_lama = $_POST['idt_lama'];
    $sku_baru = $_POST['sku_baru'];
    $idt_baru = $_POST['idt_baru'];
    $idt = $_POST['idt'];
    $btn = $_POST['deletemutasi'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $user = $_POST['user'];
    foreach ($btn as $index => $id) {
        $select = mysqli_query($conn, "SELECT
        (SELECT quantity_toko FROM toko_id WHERE id_toko = '$idt_lama') AS qty_lama,
        (SELECT quantity_toko FROM toko_id WHERE id_toko = '$idt_baru') AS qty_baru
        FROM toko_id");
        $fetch = mysqli_fetch_assoc($select);
        $qty_lama = $fetch['qty_lama'];
        $qty_baru = $fetch['qty_baru'];
        $qty_total = $qty_lama + $qty_baru;
        $qty_penambah_baru = $qty_total - $qty_baru;
        if ($fetch) {
            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES ('$idt[$index] $idt_baru mutasi', '$qty_baru',  '$qty_total', 'mutasi', '$date', '$qty_lama', '$idt_baru', '$idt[$index]', 'Perpindahan Mutasi Dari $sku_lama Ke $sku_baru', '$user')");
            if ($insert) {
                $updateskulama =  mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$qty_total' WHERE id_toko = '$idt_baru'");
                if ($updateskulama) {
                    $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES ('$idt[$index] $idt_lama mutasi', '$qty_lama', '0', 'mutasi', '$date', '-{$qty_lama}', '$idt_lama', '$idt[$index]', 'Dipindahkan stoknya ke $sku_baru', '$user')");
                    if ($insert2);
                    $updateskubaru = mysqli_query($conn, "UPDATE toko_id SET sku_toko = NULL , quantity_toko = '0', lorong = '', toko = '', berat = '', max_qty = '', tipe = '', per = '', tipe_barang = '', min_order = '', wadah = '' WHERE id_toko ='$idt_lama'");
                    if ($updateskubaru) {
                        $update = mysqli_query($conn, "UPDATE task_id SET status = 'done' WHERE id_task = '$idt[$index]'");
                    }
                }
            }
        }
    }
    echo '<script>alert("SKU ' . htmlspecialchars($sku_lama) . ' telah dihapus");';
    echo 'window.location.href = "?url=delete";</script>';
}

if (isset($_POST['CancelMutasi'])) {
    $sku_lama = $_POST['sku_lama'];
    $idt_lama = $_POST['idt_lama'];
    $sku_baru = $_POST['sku_baru'];
    $idt_baru = $_POST['idt_baru'];
    $idt = $_POST['idt'];
    $btn = $_POST['CancelMutasi'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $user = $_POST['user'];
    foreach ($btn as $index => $id) {
        $updateTaskId = mysqli_query($conn, "UPDATE task_id SET status = 'canceled' WHERE id_task = $idt[$index]");
        if ($updateTaskId) {
            $updateTokoId = mysqli_query($conn, "UPDATE toko_id SET sku_toko = null where id_toko = $idt_baru");
        } else {
            echo "gagal update toko";
        }
    }
    echo '<script>alert("SKU ' . htmlspecialchars($sku_baru) . ' telah dihapus");';
    echo 'window.location.href = "?url=delete";</script>';
}

if (isset($_POST['forward'])) {
    $idt = $_POST['idt'];
    $idp = $_POST['idp'];
    $idtoko = $_POST['idtoko'];
    $qty  =  $_POST['qty'];
    $user = $_POST['user'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');

    $select = mysqli_query($conn, "SELECT id_komponen, quantity_komponen FROM list_komponen WHERE id_product_finish = '$idp'");
    while ($fetch = mysqli_fetch_assoc($select)) {
        $qtykomp = $fetch['quantity_komponen'];
        $qtytotal = $qty * $qtykomp;
        $hitung = mysqli_num_rows($select);
        if ($hitung = 0) {
            echo '<script>
        alert("Product toko belum di koneksikan dengan komponen gudang, harap hubungi gudang!");
        </script>';
        } else {
            $idg = $fetch['id_komponen'];
            if ($select) {
                $insert = mysqli_query($conn, "INSERT INTO request_gudang(id_product, id_gudang, id_history, quantity_req, status, jenis, date) VALUES ('$idp', '$idg', '$idt', '$qtytotal', 'requested', 'mutasi', '$date')");
                if ($insert) {
                    $update = mysqli_query($conn, "UPDATE task_id SET status = 'pending' where id_task = '$idt'");
                }
            }
        }
    }
    header('location:?url=pindah&idt=' . $idt . '');
}

if (isset($_POST['mutasi2'])) {
    $idt = $_POST['idt'];
    $sku = $_POST['sku'];
    $sku1 = $_POST['sku1'];
    $user = $_POST['user'];
    $id_rekomen = $_POST['id_rekomen'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach ($idt as $index => $indexSku) {
        if (isset($sku1[$indexSku]) && isset($sku[$indexSku])) {
            $sku1_id = mysqli_real_escape_string($conn, $sku1[$indexSku]);
            $sku_id = mysqli_real_escape_string($conn, $sku[$indexSku]);
            $idRekomen = mysqli_real_escape_string($conn, $id_rekomen[$indexSku]);
            $ambil = mysqli_query($conn, "SELECT sku_toko FROM toko_id WHERE sku_toko = '$sku_id'");
            if (mysqli_num_rows($ambil) > 0) {
                echo '<script>alert("SKU sudah ada");</script>';
            } else {
                $select = mysqli_query($conn, "SELECT id_product, berat, tipe, per, tipe_barang, min_order, quantity_toko FROM toko_id WHERE sku_toko='$sku1_id'");
                $fetch = mysqli_fetch_assoc($select);
                if ($fetch) {
                    $idp = $fetch['id_product'];
                    $berat = $fetch['berat'];
                    $tipe = $fetch['tipe'];
                    $per = $fetch['per'];
                    $tipe_barang = $fetch['tipe_barang'];
                    $min_order = $fetch['min_order'];
                    $quantity = $fetch['quantity_toko'];
                    $mutasi = mysqli_query($conn, "INSERT INTO mutasitoko_id(id_toko, sku_lama, sku_baru, datetime, id_rekomen) VALUES ('$idp', '$sku1_id', '$sku_id', '$date', '$idRekomen')");
                    if ($mutasi) {
                        $idm = mysqli_insert_id($conn);
                        if (preg_match("/^(\d+)[a-z]+(\d+)$/i", $sku_id, $matches)) {
                            $angkaSebelumHuruf = $matches[1];
                            $wadahId = $matches[2];
                            switch ($angkaSebelumHuruf) {
                                case '1':
                                case '2':
                                case '3':
                                case '4':
                                    $wadah = 'donat';
                                    break;
                                case '5':
                                case '6':
                                case '7':
                                case '14':
                                    $wadah = 'mika';
                                    break;
                                case '8':
                                    $wadah = 'kardus';
                                    break;
                                case '9':
                                case '18':
                                case '19':
                                    $wadah = 'donat';
                                    break;
                                case '10':
                                case '13':
                                case '20';
                                    $wadah = 'container';
                                    break;
                            }
                            switch ($angkaSebelumHuruf) {
                                case '1':
                                case '2':
                                case '3':
                                case '4':
                                case '5':
                                case '6':
                                case '7':
                                case '15':
                                    $toko = 'A';
                                    break;
                                case '8':
                                case '9':
                                case '11':
                                case '12':
                                case '14':
                                case '16':
                                case '17':
                                    $toko = 'B';
                                    break;
                                case '10':
                                case '13':
                                case '18':
                                case '19':
                                case '20':
                                case '21':
                                    $toko = 'C';
                                    break;
                            }

                            if ($angkaSebelumHuruf >= 1 && $angkaSebelumHuruf <= 7) {
                                $lorong = $angkaSebelumHuruf;
                            } elseif ($angkaSebelumHuruf == 8 || $angkaSebelumHuruf == 9) {
                                $lorong = 5;
                            } elseif ($angkaSebelumHuruf == 10 || $angkaSebelumHuruf == 13) {
                                $lorong = 5;
                            } elseif ($angkaSebelumHuruf == 14 || $angkaSebelumHuruf == 12) {
                                $lorong = 5;
                            }
                        }
                        $sql = mysqli_query($conn, "INSERT INTO toko_id(sku_toko, id_product, berat, tipe, per, tipe_barang, lorong, toko, min_order, wadah, quantity_toko) VALUES ('$sku_id', '$idp', '$berat', '$tipe', '$per', '$tipe_barang', '$lorong', '$toko', '$min_order', '$wadah', '0')");
                        if ($sql) {
                            $insert3 = mysqli_query($conn, "INSERT INTO task_id(id_history, requester, jenis, status) VALUES ('$idm', '$user', 'mutasi', 'unprocessed')");
                            if ($insert3) {
                                $updateMutasi = mysqli_query($conn, "UPDATE rekomendasi_mutasitoko SET status = 'done' WHERE id_mutasi = '$idRekomen'");
                            }
                            if (!$insert3) {
                                echo '<script>alert("Gagal memproses mutasi");</script>';
                            }
                        } else {
                            echo '<script>alert("Gagal menambahkan SKU baru");</script>';
                        }
                    } else {
                        echo '<script>alert("Gagal melakukan mutasi");</script>';
                    }
                }
            }
        }
    }
    header('location:?url=mutasi');
}

if (isset($_POST['buttonbackduty'])) {
    $invoice = mysqli_real_escape_string($conn, $_POST['invoiceback']);
    $select = mysqli_query($conn, "SELECT invoice, checking FROM tracking WHERE invoice = '$invoice'");
    if ($select) {
        $data = mysqli_fetch_array($select);
        if ($data['checking'] == '') {
            $updatetracking = mysqli_query($conn, "UPDATE tracking SET box = '', picking = 'pending' WHERE invoice = '$invoice'");
            if ($updatetracking) {
                $query = mysqli_query($conn, "SELECT id_shop FROM shop_id WHERE invoice = '$invoice'");
                while ($dataid = mysqli_fetch_assoc($query)) {
                    $id_shop = $dataid['id_shop'];
                    $transaksi = mysqli_query($conn, "SELECT uniq_transaksi,id_transaksi from transaksi_toko WHERE id_history = '$id_shop' LIMIT 1");
                    $datauniq = mysqli_fetch_assoc($transaksi);
                    if ($datauniq) {
                        $uniq = $datauniq['uniq_transaksi'];
                        $id = $datauniq['id_transaksi'];
                        $update  = mysqli_query($conn, "UPDATE transaksi_toko SET uniq_transaksi = '$uniq cancel' WHERE id_transaksi = '$id'");
                        if ($update) {
                            header('location:?url=kelompok');
                            // Lanjutkan dengan tindakan lain jika perlu
                        } else {
                            echo "Failed to update transaksi information: " . mysqli_error($conn);
                            // Tangani kegagalan pembaruan
                        }
                    }
                }
                // Check if the update was successful
                // Redirect the user
                // Stop further execution
            } else {
                echo "Failed to update tracking information.";
                // Handle update failure
            }
        } else {
            echo '<script>
            alert("Invoice Ini Sudah Ada Di Checking");
            window.location.href = "?url=kelompok";
        </script>';
        }
    } else {
        // Handle query failure
        echo "Failed to fetch tracking information.";
    }
    // header('location:?url=mutasi');
}

if (isset($_POST['mutasi3'])) {
    $idt = $_POST['idt'];
    $sku = $_POST['sku'];
    $sku1 = $_POST['sku1'];
    $user = $_POST['user'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $count = count($idt);
    foreach ($idt as $i => $id) {
        $sku1_value = $sku1[$i];
        $sku_value = $sku[$i];

        $select = mysqli_query($conn, "SELECT id_product, berat, tipe, per, tipe_barang, min_order, quantity_toko FROM toko_id WHERE sku_toko='$sku1_value'");
        $fetch = mysqli_fetch_assoc($select);

        if ($fetch) {
            $idp = $fetch['id_product'];
            $berat = $fetch['berat'];
            $tipe = $fetch['tipe'];
            $per = $fetch['per'];
            $tipe_barang = $fetch['tipe_barang'];
            $min_order = $fetch['min_order'];
            $quantity = $fetch['quantity_toko'];

            $mutasi = mysqli_query($conn, "INSERT INTO mutasitoko_id(id_toko, sku_lama, sku_baru, datetime) VALUES ('$id', '$sku1_value', '$sku_value', '$date')");

            if ($mutasi) {
                $idm = mysqli_insert_id($conn);

                if (preg_match("/^(\d+)[a-z]+(\d+)$/i", $sku_value, $matches)) {
                    $angkaSebelumHuruf = $matches[1];
                    $wadahId = $matches[2];

                    switch ($angkaSebelumHuruf) {
                        case '1':
                        case '2':
                        case '3':
                        case '4':
                            $wadah = 'donat';
                            break;
                        case '5':
                        case '6':
                        case '7':
                        case '14':
                            $wadah = 'mika';
                            break;
                        case '8':
                            $wadah = 'kardus';
                            break;
                        case '9':
                        case '18':
                        case '19':
                            $wadah = 'donat';
                            break;
                        case '10':
                        case '13':
                        case '20';
                            $wadah = 'container';
                            break;
                    }

                    switch ($angkaSebelumHuruf) {
                        case '1':
                        case '2':
                        case '3':
                        case '4':
                        case '5':
                        case '6':
                        case '7':                    
                            $toko = 'A';
                            break;
                        case '8':
                        case '9':
                        case '11':
                        case '12':
                        case '14':
                            $toko = 'B';
                            break;
                        case '10':
                        case '13':
                        case '18':
                        case '19':
                        case '20':
                            $toko = 'C';
                            break;
                    }

                    if ($angkaSebelumHuruf >= 1 && $angkaSebelumHuruf <= 7) {
                        $lorong = $angkaSebelumHuruf;
                    } elseif ($angkaSebelumHuruf == 8 || $angkaSebelumHuruf == 9) {
                        $lorong = 5;
                    } elseif ($angkaSebelumHuruf >= 10 && $angkaSebelumHuruf <= 14) {
                        $lorong = 5;
                    }
                }

                $sql = mysqli_query($conn, "INSERT INTO toko_id(sku_toko, id_product, berat, tipe, per, tipe_barang, lorong, toko, min_order, wadah, quantity_toko) VALUES ('$sku_value', '$idp', '$berat', '$tipe', '$per', '$tipe_barang', '$lorong', '$toko', '$min_order', '$wadah', '0')");

                if ($sql) {
                    $insert3 = mysqli_query($conn, "INSERT INTO task_id(id_history, requester, jenis, status) VALUES ('$idm', '$user', 'mutasi', 'unprocessed')");
                    if (!$insert3) {
                        echo '<script>alert("Gagal memproses mutasi");</script>';
                    }
                } else {
                    echo '<script>alert("Gagal menambahkan SKU baru");</script>';
                }
            } else {
                echo '<script>alert("Gagal melakukan mutasi");</script>';
            }
        }
    }
    header('location:?url=mutasi');
}

if (isset($_POST['returbarang'])) {
    $qty1 = $_POST['jumlah'];
    $qty2 = $_POST['jumlah1'];
    $invoice = $_POST['inv'];
    $resi = $_POST['resi'];
    $user = $_POST['user'];
    $ids = $_POST['ids'];
    $olshop = $_POST['olshop'];
    $idp = $_POST['idp'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $note = $_POST['note'];
    $select = mysqli_query($conn, "SELECT sku_toko, id_product FROM toko_id WHERE id_toko = '$idp'");
    $data = mysqli_fetch_assoc($select);
    $sku = $data['sku_toko'];
    $idProduct = $data['id_product'];
    if ($sku !== NULL) {
        $insert = mysqli_query($conn, "INSERT INTO retur_id (id_shop, invoice, resi, quantity, date, id_toko, reference) VALUES ('$ids', '$invoice', '$resi', '$qty1', '$date', '$idp', '$olshop')");
        if ($insert) {
            if ($qty1 == $qty2) {
                $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'full retur' WHERE id_shop = '$ids'");
            } else {
                $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'parsial retur' WHERE id_shop = '$ids'");
            }
            $select = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko = '$idp'");
            $fetch = mysqli_fetch_array($select);
            $qty = $fetch['quantity_toko'];
            $hasil = $qty + $qty1;
            if ($select) {
                $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES ('$ids $idp retur', '$qty', '$hasil', 'retur', '$date', '$qty1', '$idp', '$ids', '$note', '$user')");
                if ($insert2) {
                    $updateqty = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$hasil' WHERE id_toko = '$idp'");
                    if ($updateqty) {
                        $update = mysqli_query($conn, "UPDATE tracking SET status_retur = 'Selesai', note_track = 'Sudah Di Retur' WHERE invoice = '$invoice'");
                        if ($update) {
                            header('Location:?url=kembali&noresi=' . $invoice); // Corrected redirection syntax
                        }
                    }
                }
            }
        }
    } else {
        $ambil = mysqli_query($conn, "SELECT id_toko FROM toko_id WHERE id_product = '$idProduct' AND sku_toko IS NOT NULL");
        $idt = mysqli_fetch_assoc($ambil)['id_toko'];
        if ($ambil) {
            $insert = mysqli_query($conn, "INSERT INTO retur_id (id_shop, invoice, resi, quantity, date, id_toko, reference) VALUES ('$ids', '$invoice', '$resi', '$qty1', '$date', '$idt', '$olshop')");
            if ($insert) {
                if ($qty1 == $qty2) {
                    $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'full retur' WHERE id_shop = '$ids'");
                } else {
                    $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'parsial retur' WHERE id_shop = '$ids'");
                }
                $select = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko = '$idt'");
                $fetch = mysqli_fetch_array($select);
                $qty = $fetch['quantity_toko'];
                $hasil = $qty + $qty1;
                if ($select) {
                    $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, id_history, note_transaksi, nama_user) VALUES ('$ids $idt retur', '$qty', '$hasil', 'retur', '$date', '$qty1', '$idt', '$ids', '$note', '$user')");
                    if ($insert2) {
                        $updateqty = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$hasil' WHERE id_toko = '$idt'");
                        if ($updateqty) {
                            $update = mysqli_query($conn, "UPDATE tracking SET status_retur = 'Selesai', note_track = 'Sudah Di Retur' WHERE invoice = '$invoice'");
                            if ($update) {
                                header('Location:?url=kembali&noresi=' . $invoice); // Corrected redirection syntax
                            }
                        }
                    }
                }
            }
        }
    }
}

if (isset($_POST['gudangretur'])) {
    $qty1 = $_POST['jumlah'];
    $qty2 = $_POST['jumlah1'];
    $invoice = $_POST['inv'];
    $resi = $_POST['resi'];
    $user = $_POST['user'];
    $ids = $_POST['ids'];
    $olshop = $_POST['olshop'];
    $idp = $_POST['idp'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $select = mysqli_query($conn, "SELECT sku_toko, id_product FROM toko_id WHERE id_toko = '$idp'");
    $data = mysqli_fetch_assoc($select);
    $sku = $data['sku_toko'];
    $idProduct = $data['id_product'];
    $insert = mysqli_query($conn, "INSERT INTO retur_id (id_shop, invoice, resi, quantity, date, id_toko, reference) VALUES ('$ids', '$invoice', '$resi', '$qty1', '$date', '$idp', '$olshop')");
    if ($insert) {
        if ($qty1 == $qty2) {
            $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'full retur' WHERE id_shop = '$ids'");
        } else {
            $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'parsial retur' WHERE id_shop = '$ids'");
        }
        $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE id_toko = '$idp'");
        $idProduct = mysqli_fetch_array($select)['id_product'];
        if ($select) {
            $komponenlist = mysqli_query($conn, "SELECT id_komponen, quantity_komponen FROM list_komponen WHERE id_product_finish = '$idProduct'");
            while ($data = mysqli_fetch_array($komponenlist)) {
                $idkomp = $data['id_komponen'];
                $qtytotal = $qty1 * $data['quantity_komponen'];
                $insert2 = mysqli_query($conn, "INSERT INTO request_gudang (id_product, id_gudang, id_history, quantity_req, status, jenis, date) VALUES ('$idProduct', '$idkomp', '$ids', '$qtytotal', 'requested', 'retur', '$date')");
            }
        }
    }
}
if (isset($_POST['noteretur'])) {
    $inv = $_POST['inv'];
    $note = $_POST['note'];

    $update = mysqli_query($conn, "UPDATE tracking SET dikurir = '$note' WHERE invoice = '$inv'");
    if ($update) {
        echo '<script>
            alert("Invoice ' . $inv . ' telah di retur!");
            window.location.href = "?url=returin";
        </script>';
        exit;
    }
}

if (isset($_POST["tokpedsebulan"])) {
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    mysqli_begin_transaction($conn);
    try {
        foreach ($data as $row) {
            $invoice = mysqli_real_escape_string($conn, $row[0]);
            $status1 = $row[1];
            $status2 = $row[2];
            $status3 = $row[3];
            $status = trim("$status1 $status2 $status3");
            $resi = mysqli_real_escape_string($conn, $row[37]);
            $nama_product = mysqli_real_escape_string($conn, $row[7]);
            $varian = mysqli_real_escape_string($conn, $row[8]);
            $nama_final = "$nama_product - $varian";

            // Update tracking table
            $update_tracking = "UPDATE tracking SET status_mp = '$status', no_resi = '$resi' WHERE invoice = '$invoice'";
            $update_tracking_result = mysqli_query($conn, $update_tracking);
            if (!$update_tracking_result) {
                throw new Exception("Failed to update tracking table for invoice $invoice: " . mysqli_error($conn));
            }

            // Update shop_id table
            $update_shop_id = "UPDATE shop_id SET status_mp = '$status', resi = '$resi' WHERE invoice = '$invoice'";
            $update_shop_id_result = mysqli_query($conn, $update_shop_id);
            if (!$update_shop_id_result) {
                throw new Exception("Failed to update shop_id table for invoice $invoice: " . mysqli_error($conn));
            }

            // Check existence in shop_id
            $exist = mysqli_query($conn, "SELECT DISTINCT invoice, status_mp FROM shop_id WHERE invoice = '$invoice' AND olshop = 'Tokopedia'");
            if ($exist && mysqli_num_rows($exist) > 0) {
                $exist_row = mysqli_fetch_assoc($exist);
                $shopinvoice = $exist_row['invoice'];
                $status_mp = $exist_row['status_mp'];

                // Check existence in history_tokped
                $selectexist = mysqli_query($conn, "SELECT * FROM history_tokped WHERE invoice = '$shopinvoice' AND status_terakhir = '$status_mp'");
                if (mysqli_num_rows($selectexist) == 0) {
                    $unique_id = "$shopinvoice $status $date";
                    $theonlyexception = mysqli_query($conn, "SELECT * FROM history_tokped WHERE unique_id = '$unique_id'");
                    if (mysqli_num_rows($theonlyexception) == 0) {
                        $insert_history = "INSERT INTO history_tokped(unique_id, invoice, status_terakhir, date) VALUES ('$unique_id', '$invoice', '$status', '$date')";
                        if (!mysqli_query($conn, $insert_history)) {
                            throw new Exception("Failed to insert into history_tokped for unique_id $unique_id: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo $e->getMessage();
    }

    header('Location: ?url=kelompok');
}


if (isset($_POST["importshopeesebulan"])) {
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    if (isset($data[0][4]) && trim($data[0][4]) === "Opsi Pengiriman") {
        echo "Proses dihentikan karena field 4 pada baris pertama berisi 'Opsi Pengiriman'.";
        exit;
    }
    mysqli_begin_transaction($conn);
    try {
        foreach ($data as $row) {
            $invoice = mysqli_real_escape_string($conn, $row[0]);
            $resi = mysqli_real_escape_string($conn, $row[4]);
            $status = mysqli_real_escape_string($conn, $row[1]);
            $batal = mysqli_real_escape_string($conn, $row[2]);
            $statusRetur = mysqli_real_escape_string($conn, $row[3]);
            $sku = mysqli_real_escape_string($conn, $row[14]);
            $combinedStatus = $status . ' ' . $batal . ' ' . $statusRetur;
            $update_tracking = "UPDATE tracking SET status_mp = '$combinedStatus', no_resi = '$resi' WHERE invoice = '$invoice'";
            $update_tracking_result = mysqli_query($conn, $update_tracking);
            if ($update_tracking_result) {
                $update_shop_id = "UPDATE shop_id SET status_mp = '$combinedStatus' WHERE invoice = '$invoice' AND sku_toko = '$sku'";
                $update_shop_id_result = mysqli_query($conn, $update_shop_id);
                if ($update_shop_id_result) {
                    $update_resi_shop_id = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi' WHERE invoice = '$invoice'");
                }
                if (!$update_shop_id_result) {
                    throw new Exception("Gagal memperbarui data pada tabel shop_id.");
                }
            } else {
                throw new Exception("Gagal memperbarui data pada tabel tracking.");
            }
        }
        mysqli_commit($conn);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo $e->getMessage();
    }
    header('location:?url=kelompok');
}


if (isset($_POST["importinstant"])) {
    // Dapatkan informasi file yang diunggah
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    // Buka file Excel untuk dibaca
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    // Baca baris pertama sebagai nama header kolom
    $header = [];
    foreach ($worksheet->getRowIterator(1, 1) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false); // Loop semua sel, bahkan yang kosong
        foreach ($cellIterator as $cell) {
            $header[] = $cell->getValue();
        }
    }
    // Loop untuk membaca setiap baris dalam file Excel
    foreach ($worksheet->getRowIterator(2) as $row) {
        $data = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false); // Loop semua sel, bahkan yang kosong
        foreach ($cellIterator as $cell) {
            $data[] = $cell->getValue();
        }
        // Dapatkan data dari baris Excel
        $nopesanan = $data[array_search('No. Pesanan', $header)];
        $status = $data[array_search('Status Pesanan', $header)];
        $statusRetur = $data[array_search('Status Pembatalan/ Pengembalian', $header)];
        $resi = $data[array_search('No. Resi', $header)];
        $kurir = $data[array_search('Opsi Pengiriman', $header)];
        $tglkirim = $data[array_search('Waktu Pengiriman Diatur', $header)];
        $tglbayar = $data[array_search('Waktu Pembayaran Dilakukan', $header)];
        $nama_product = $data[array_search('Nama Produk', $header)];
        $string = strlen($nama_product);
        $varian = substr($nama_product, $string - 20, 20);
        $variasi = $data[array_search('Nama Variasi', $header)];
        $sku = $data[array_search('Nomor Referensi SKU', $header)];
        $jumlah = $data[array_search('Jumlah', $header)];
        $penerima = $data[array_search('Nama Penerima', $header)];
        date_default_timezone_set('Asia/Jakarta');
        $date = date('Y-m-d H:i:s');
        // ... tambahkan kolom lainnya sesuai dengan struktur tabel Anda
        if (strpos($nopesanan, '2') === 0) {
            // Memeriksa apakah $pembayaran adalah datetime yang valid
            if ($tglbayar != '-') {
                $tanggal = new DateTime($tglkirim);
                $tanggal_kirim = $tanggal->format('Y-m-d H:i:s'); // Format tanggal ke dalam bentuk yang sesuai dengan MySQL
                $tanggal1 = new DateTime($tglbayar);
                $tanggal_bayar = $tanggal1->format('Y-m-d H:i:s');
                $select = mysqli_query($conn, "SELECT id_product, id_toko,sku_toko FROM toko_id WHERE sku_toko='$sku'");
                $dataselect = mysqli_fetch_array($select);
                if ($dataselect) {
                    $id_toko = $dataselect['id_toko'];
                } else {
                    $id_toko = '0';
                }
                if ($status == 'Perlu Dikirim') {
                    $exceptshopee = mysqli_query($conn, "SELECT id_orderitem FROM shop_id WHERE id_orderitem = '$nopesanan $sku $varian $variasi' ");
                    $cancel = mysqli_num_rows($exceptshopee);
                    if ($cancel == 0) {
                        if ($kurir == 'Instant-SPX Instant' || $kurir == 'Instant-GrabExpress Instant' || $kurir == 'Instant-GoSend Instant' || $kurir == 'Same Day-SPX Sameday' || $kurir == 'GoSend Instant' || $kurir == 'GrabExpress Sameday' || $kurir == 'SPX Instant' || $kurir == 'GrabExpress Instant' || $kurir == 'Same Day-Anteraja Sameday') {
                            // Assuming you have a database connection object named $conn
                            $stmt = $conn->prepare("INSERT INTO shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, resi, tanggal_pengiriman, nama_product, olshop) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            // Bind parameters
                            $stmt->bind_param("ssssssssssss", $nopesanan_sku_varian_variasi, $nopesanan, $tanggal_bayar, $id_toko, $sku, $jumlah, $penerima, $kurir, $resi, $tanggal_kirim, $nama_product_variasi, $olshop);
                            // Set parameter values
                            $nopesanan_sku_varian_variasi = "$nopesanan $sku $varian $variasi";
                            $nama_product_variasi = "$nama_product ($variasi)";
                            $olshop = 'Shopee';
                            // Execute the statement
                            if ($stmt->execute()) {
                                $stmt2 = $conn->prepare("INSERT INTO shop_id_miror (id_orderitem, invoice, resi, tanggal_bayar, nama_product, sku_toko, jumlah, id_product, olshop, date_load) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt2->bind_param("ssssssssss", $nopesanan_sku_varian_variasi, $nopesanan, $resi, $tanggal_bayar, $nama_product_variasi, $sku, $jumlah, $id_toko, $olshop, $date);
                                if ($stmt2->execute()) {
                                } else {
                                    echo "Error: " . $stmt->error;
                                }
                                if (strstr($kurir, "JNE")) {
                                    $namaKurir = 'JNE';
                                } else if (strstr($kurir, "Rekomendasi")) {
                                    $namaKurir = 'rekomendasi';
                                } else if (strstr($kurir, "SiCepat") || $kurir == 'Next Day-Sicepat BEST' || $kurir == 'Kargo-Sicepat Gokil') {
                                    $namaKurir = 'sicepat';
                                } else if ($kurir == 'Kargo-J&T Cargo') {
                                    $namaKurir = 'j&t cargo';
                                } else if (strstr($kurir, "Paxel")) {
                                    $namaKurir = 'paxel';
                                } else if ($kurir == 'GTL(Regular)') {
                                    $namaKurir = 'gtl';
                                } else if (strstr($kurir, "J&T") && $kurir != 'Kargo-J&T Cargo') {
                                    $namaKurir = 'j&t';
                                } else if (strstr($kurir, "SPX") && $kurir != 'Instant-SPX Instant' && $kurir != 'Same Day-SPX Sameday') {
                                    $namaKurir = 'shopee';
                                } else {
                                    $namaKurir = NULL;
                                }
                                if ($namaKurir == NULL) {
                                    $waktu = strtotime($date);
                                    $alert = strtotime('+2 hour', $waktu);
                                    $datesla = date('Y-m-d H:i:s', $alert);
                                    $namaKurir = 'instant';
                                } else {
                                    $sla = mysqli_query($conn, "SELECT deadline FROM schedule_id WHERE kurir = '$namaKurir'");
                                    $datasla = mysqli_fetch_assoc($sla);
                                    $alert = $datasla['deadline'];
                                    $tanggal = date('Y-m-d');
                                    $datetime = $tanggal . ' ' . $alert;
                                    $datesla = new DateTime($datetime);
                                    $datesla = $datesla->format('Y-m-d H:i:s');
                                }
                                $exclude = mysqli_query($conn, "SELECT invoice FROM tracking WHERE invoice = '$nopesanan'");
                                $excluderows = mysqli_num_rows($exclude);
                                if ($excluderows == 0) {
                                    $inserttracking = "INSERT INTO tracking (time_load, invoice, no_resi, nama_kurir,alert) VALUES ('$date','$nopesanan', '$resi', '$namaKurir','$datesla')";
                                    if ($conn->query($inserttracking) !== TRUE) {
                                        echo "Error: " . $inserttracking . "<br>" . $conn->error;
                                    }
                                } else {
                                    $updatet = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi', status_mp = '$status $statusRetur' WHERE invoice = '$nopesanan'");
                                }
                            } else {
                            }
                        } else {
                        }
                    } else {
                        $updatet = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi', status_mp = '$status $statusRetur' WHERE invoice = '$nopesanan'");
                        $update = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi' WHERE id_orderitem = '$nopesanan $sku $varian $variasi'");
                    }
                } else {
                }
            }
        }
    }
    header('location:?url=kelompok');
}

if (isset($_POST['returdata'])) {
    $qty1 = $_POST['jumlah'];
    $qty2 = $_POST['jumlah1'];
    $invoice = $_POST['inv'];
    $resi = $_POST['resi'];
    $user = $_POST['user'];
    $ids = $_POST['ids'];
    $olshop = $_POST['olshop'];
    $idp = $_POST['idp'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $note = $_POST['note'];
    $insert = mysqli_query($conn, "INSERT INTO retur_id (id_shop, invoice, resi, quantity, date, id_toko, reference) VALUES ('$ids', '$invoice', '$resi', '$qty1', '$date', '$idp', '$olshop')");
    if ($insert) {
        if ($qty1 == $qty2) { // Corrected comparison operator
            $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'full retur' WHERE id_shop = '$ids'");
        } else {
            $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'parsial retur' WHERE id_shop = '$ids'");
        }
        header('Location:?url=kembali&noresi=' . $invoice); // Corrected redirection syntax
    }
}


if (isset($_POST['shopid'])) {
    $ids = $_POST['ids'];
    $sku = $_POST['sku'];
    $olshop = $_POST['olshop'];
    $higher = strtoupper($sku);
    $select = mysqli_query($conn, "SELECT id_toko FROM toko_id WHERE sku_toko = '$sku'");
    $data = mysqli_fetch_array($select);
    $idt = $data['id_toko'];
    if ($select) {
        $update = mysqli_query($conn, "UPDATE temporary_shop_id SET sku_toko = '$higher', id_product = '$idt' WHERE id_temp = '$ids'");
        if ($update) {
            if ($olshop == 'Shopee') {
                header('Location:?url=temporaryshopee');
            } else {
                header('Location:?url=temporary');
            }
        }
    }
}

if (isset($_POST['shopinsert'])) {
    $select = mysqli_query($conn, "SELECT * FROM temporary_shop_id WHERE olshop = 'Tokopedia'");
    while ($data = mysqli_fetch_array($select)) {
        $key = $data['id_orderitem'];
        $status_order = $data['status_order'];
        $invoice = $data['invoice'];
        $tanggal_bayar = $data['tanggal_bayar'];
        $nama = $data['nama_product'];
        $sku_toko = $data['sku_toko'];
        $jumlah = $data['jumlah'];
        $penerima = $data['penerima'];
        $kurir = $data['kurir'];
        $tipe = $data['tipe'];
        $resi = $data['resi'];
        $tanggal_pengiriman = $data['tanggal_pengiriman'];
        $waktu_pengiriman = $data['waktu_pengiriman'];
        $id_product = $data['id_product'];
        $olshop = $data['olshop'];
        $statusMP = $data['status_mp'];
        date_default_timezone_set('Asia/Jakarta');
        $date = date('Y-m-d H:i:s');

        $stmt = mysqli_prepare($conn, "SELECT id_orderitem FROM shop_id WHERE id_orderitem = ?");
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        $cancel = mysqli_num_rows(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($cancel == 0) {
            $query = "INSERT INTO shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, tipe, resi, tanggal_pengiriman, waktu_pengiriman, nama_product, olshop, status_mp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssssssssssssss", $key, $invoice, $tanggal_bayar, $id_product, $sku_toko, $jumlah, $penerima, $kurir, $tipe, $resi, $tanggal_pengiriman, $waktu_pengiriman, $nama, $olshop, $statusMP);
            if (mysqli_stmt_execute($stmt)) {
                $stmt2 = mysqli_prepare($conn, "SELECT id_orderitem FROM shop_id_miror WHERE id_orderitem = ?");
                mysqli_stmt_bind_param($stmt2, "s", $key);
                mysqli_stmt_execute($stmt2);
                $cancel2 = mysqli_num_rows(mysqli_stmt_get_result($stmt2));
                mysqli_stmt_close($stmt2);

                if ($cancel2 == 0) {
                    $query2 = "INSERT INTO shop_id_miror (id_orderitem, invoice, resi, tanggal_bayar, nama_product, sku_toko, jumlah, id_product, olshop, date_load) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt2 = mysqli_prepare($conn, $query2);
                    mysqli_stmt_bind_param($stmt2, "ssssssssss", $key, $invoice, $resi, $tanggal_bayar, $nama, $sku_toko, $jumlah, $id_product, $olshop, $date);
                    if (mysqli_stmt_execute($stmt2)) {
                        if (strstr($kurir, "JNE")) {
                            $namaKurir = 'JNE';
                        } else if (strstr($kurir, "Rekomendasi")) {
                            $namaKurir = 'rekomendasi';
                        } else if (strstr($kurir, "SiCepat") || $kurir == 'Next Day-Sicepat BEST' || $kurir == 'Kargo-Sicepat Gokil') {
                            $namaKurir = 'sicepat';
                        } else if ($kurir == 'Kargo-J&T Cargo') {
                            $namaKurir = 'j&t cargo';
                        } else if (strstr($kurir, "Paxel")) {
                            $namaKurir = 'paxel';
                        } else if ($kurir == 'GTL(Regular)') {
                            $namaKurir = 'gtl';
                        } else if (strstr($kurir, "J&T") && $kurir != 'Kargo-J&T Cargo') {
                            $namaKurir = 'j&t';
                        } else {
                            $namaKurir = NULL;
                        }
                        if ($namaKurir == NULL) {
                            $waktu = strtotime($date);
                            $alert = strtotime('+2 hour', $waktu);
                            $datesla = date('Y-m-d H:i:s', $alert);
                            $namaKurir = 'instant';
                        } else {
                            $sla_stmt = mysqli_prepare($conn, "SELECT deadline FROM schedule_id WHERE kurir = ?");
                            mysqli_stmt_bind_param($sla_stmt, "s", $namaKurir);
                            mysqli_stmt_execute($sla_stmt);
                            $result_sla = mysqli_stmt_get_result($sla_stmt);
                            $datasla = mysqli_fetch_assoc($result_sla);
                            mysqli_stmt_close($sla_stmt);
                            $alert = $datasla['deadline'];
                            $tanggal = date('Y-m-d');
                            $datetime = $tanggal . ' ' . $alert;
                            $datesla = new DateTime($datetime);
                            $datesla = $datesla->format('Y-m-d H:i:s');
                        }
                        $exclude_stmt = mysqli_prepare($conn, "SELECT invoice FROM tracking WHERE invoice = ?");
                        mysqli_stmt_bind_param($exclude_stmt, "s", $invoice);
                        mysqli_stmt_execute($exclude_stmt);
                        $excluderows = mysqli_num_rows(mysqli_stmt_get_result($exclude_stmt));
                        mysqli_stmt_close($exclude_stmt);

                        if ($excluderows == 0) {
                            $inserttracking = "INSERT INTO tracking (time_load, invoice, no_resi, status_mp, nama_kurir, alert) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt_track = mysqli_prepare($conn, $inserttracking);
                            mysqli_stmt_bind_param($stmt_track, "ssssss", $date, $invoice, $resi, $statusMP, $namaKurir, $datesla);
                            if (mysqli_stmt_execute($stmt_track)) {
                                $selectshop_stmt = mysqli_prepare($conn, "SELECT invoice FROM history_tokped WHERE invoice = ? AND status_terakhir = ?");
                                mysqli_stmt_bind_param($selectshop_stmt, "ss", $invoice, $statusMP);
                                mysqli_stmt_execute($selectshop_stmt);
                                $existhistory = mysqli_num_rows(mysqli_stmt_get_result($selectshop_stmt));
                                mysqli_stmt_close($selectshop_stmt);

                                if ($existhistory == 0) {
                                    $unique_id = "$invoice $statusMP $date";
                                    $history_stmt = mysqli_prepare($conn, "SELECT * FROM history_tokped WHERE unique_id = ?");
                                    mysqli_stmt_bind_param($history_stmt, "s", $unique_id);
                                    mysqli_stmt_execute($history_stmt);
                                    if (mysqli_num_rows(mysqli_stmt_get_result($history_stmt)) == 0) {
                                        $insert_history = mysqli_prepare($conn, "INSERT INTO history_tokped(unique_id, invoice, status_terakhir, date) VALUES (?, ?, ?, ?)");
                                        mysqli_stmt_bind_param($insert_history, "ssss", $unique_id, $invoice, $statusMP, $date);
                                        mysqli_stmt_execute($insert_history);
                                        mysqli_stmt_close($insert_history);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    // Delete all records from temporary_shop_id after processing
    $delete_temp = mysqli_prepare($conn, "DELETE FROM temporary_shop_id WHERE olshop = ?");
    $olshop = "Tokopedia";
    mysqli_stmt_bind_param($delete_temp, "s", $olshop);
    mysqli_stmt_execute($delete_temp);
    mysqli_stmt_close($delete_temp);
    header('Location: ?url=kelompok');
}

if (isset($_POST['shopinsertshopee'])) {
    $select = mysqli_query($conn, "SELECT * FROM temporary_shop_id WHERE olshop = 'Shopee'");
    while ($data = mysqli_fetch_array($select)) {
        $key = $data['id_orderitem'];
        $status_order = $data['status_order'];
        $invoice = $data['invoice'];
        $tanggal_bayar = $data['tanggal_bayar'];
        $nama = $data['nama_product'];
        $sku_toko = $data['sku_toko'];
        $jumlah = $data['jumlah'];
        $penerima = $data['penerima'];
        $kurir = $data['kurir'];
        $tipe = $data['tipe'];
        $resi = $data['resi'];
        $tanggal_pengiriman = $data['tanggal_pengiriman'];
        $waktu_pengiriman = $data['waktu_pengiriman'];
        $id_product = $data['id_product'];
        $olshop = $data['olshop'];
        $statusMP = $data['status_mp'];
        date_default_timezone_set('Asia/Jakarta');
        $date = date('Y-m-d H:i:s');
        $except = mysqli_query($conn, "SELECT id_orderitem FROM shop_id WHERE id_orderitem = '$key'");
        $cancel = mysqli_num_rows($except);
        if ($cancel == 0) {
            $stmt = $conn->prepare("INSERT INTO shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, tipe, resi, tanggal_pengiriman, waktu_pengiriman, nama_product, olshop, status_mp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssssssss", $key, $invoice, $tanggal_bayar, $id_product, $sku_toko, $jumlah, $penerima, $kurir, $tipe, $resi, $tanggal_pengiriman, $waktu_pengiriman, $nama, $olshop, $statusMP);
            if ($stmt->execute()) {
                $except2 = mysqli_query($conn, "SELECT id_orderitem FROM shop_id_miror WHERE id_orderitem = '$key'");
                $cancel2 = mysqli_num_rows($except2);
                if ($cancel2 == 0) {
                    $stmt2 = $conn->prepare("INSERT INTO shop_id_miror (id_orderitem, invoice, resi, tanggal_bayar, nama_product, sku_toko, jumlah, id_product, olshop, date_load) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt2->bind_param("ssssssssss", $key, $invoice, $resi, $tanggal_bayar, $nama, $sku_toko, $jumlah, $id_product, $olshop, $date);
                    if ($stmt2->execute()) {
                        if (strstr($kurir, "JNE")) {
                            $namaKurir = 'JNE';
                        } else if (stripos($kurir, "Rekomendasi") !== false) {
                            $namaKurir = 'rekomendasi';
                        } else if (stripos($kurir, "SiCepat") !== false || $kurir == 'Next Day-Sicepat BEST' || $kurir == 'Kargo-Sicepat Gokil') {
                            $namaKurir = 'sicepat';
                        } else if (stripos($kurir, "j&t") !== false && (stripos($kurir, "Cargo") !== false || stripos($kurir, "Kargo") !== false)) {
                            $namaKurir = 'j&t cargo';  // Prioritaskan untuk "j&t" dengan "Cargo" atau "Kargo"
                        } else if (stripos($kurir, "j&t") !== false) {
                            $namaKurir = 'j&t';  // Untuk "j&t" tanpa "Cargo" atau "Kargo"
                        } else if (stripos($kurir, "Paxel") !== false) {
                            $namaKurir = 'paxel';
                        } else if ($kurir == 'GTL(Regular)') {
                            $namaKurir = 'gtl';
                        } else if ((stripos($kurir, "SPX") !== false || $kurir == 'Hemat') && stripos($kurir, 'Sameday') === false && stripos($kurir, 'Instant') === false) {
                            $namaKurir = 'shopee';
                        } else {
                            $namaKurir = NULL;
                        }
                        if ($namaKurir == NULL) {
                            $waktu = strtotime($date);
                            $alert = strtotime('+2 hour', $waktu);
                            $datesla = date('Y-m-d H:i:s', $alert);
                            $namaKurir = 'instant';
                        } else {
                            $sla = mysqli_query($conn, "SELECT deadline FROM schedule_id WHERE kurir = '$namaKurir'");
                            $datasla = mysqli_fetch_assoc($sla);
                            $alert = $datasla['deadline'];
                            $tanggal = date('Y-m-d');
                            $datetime = $tanggal . ' ' . $alert;
                            $datesla = new DateTime($datetime);
                            $datesla = $datesla->format('Y-m-d H:i:s');
                        }
                        $exclude = mysqli_query($conn, "SELECT invoice FROM tracking WHERE invoice = '$invoice'");
                        $excluderows = mysqli_num_rows($exclude);
                        if ($excluderows == 0) {
                            $inserttracking = "INSERT INTO tracking (time_load, invoice, no_resi, nama_kurir, alert, status_mp) VALUES ('$date','$invoice', '$resi', '$namaKurir','$datesla', '$statusMP')";
                            if ($conn->query($inserttracking) !== TRUE) {
                                echo "Error: " . $inserttracking . "<br>" . $conn->error;
                            }
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
    $delete_temp = mysqli_query($conn, "DELETE FROM temporary_shop_id WHERE olshop = 'Shopee'");
    if ($delete_temp !== TRUE) {
        echo "Error deleting records: " . $conn->error;
    }
    header('Location: ?url=kelompok');
}


if (isset($_POST['reversepick'])) {
    $ids = $_POST['ids'];
    $idp = $_POST['idp'];
    $qty = $_POST['qty'];
    $number = $_POST['number'];
    $resi = $_POST['resi'];
    $invoices = $_POST['invoices'];
    $user = $_POST['user'];
    $cek = $_POST['req'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach ($cek as $index => $selectedId) {
        if ($qty[$index] == $number[$index]) {
            // echo $qty[$index] . " sama dengan " . $number[$index];
            $selecttoko = mysqli_query($conn, "SELECT quantity_toko, id_toko, id_product FROM toko_id WHERE id_toko = '$idp[$index]'");
            if ($selecttoko && $datatoko = mysqli_fetch_assoc($selecttoko)) {
                $quantitytoko = $datatoko['quantity_toko'];
                $id_toko = $datatoko['id_toko'];
                $idProduct = $datatoko['id_product'];
                $tambah = $quantitytoko + $qty[$index];
                $selectstatus = mysqli_query($conn, "SELECT status_pick, output FROM shop_id WHERE id_shop = '$ids[$index]'");
                if ($selectstatus && $assoc = mysqli_fetch_assoc($selectstatus)) {
                    if ($assoc['status_pick'] == 'done' && $assoc['output'] == 'toko') {
                        $select = mysqli_query($conn, "SELECT id_transaksi FROM transaksi_toko WHERE uniq_transaksi = '$ids[$index] $idp[$index] cancel'");
                        if ($select && mysqli_num_rows($select) == 0) {
                            $insert = mysqli_query($conn, "INSERT INTO transaksi_toko (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, date, quantity, id_toko, nama_user, id_history) VALUES ('$ids[$index] $idp[$index] cancel', '$quantitytoko', '$tambah', 'order cancel', '$date', '$qty[$index]', '$idp[$index]', '$user', '$ids[$index]')");
                            if ($insert) {
                                $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$tambah' WHERE id_toko = '$idp[$index]'");
                                if ($update) {
                                    $updatestat = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids[$index]'");
                                }
                            }
                        }
                    } elseif ($assoc['status_pick'] == '') {
                        $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids[$index]'");
                        if ($update) {
                            echo 'data belum di picking ';
                        }
                    } elseif ($assoc['status_pick'] == 'done' && $assoc['output'] == 'gudang') {
                        $selectkomp = mysqli_query($conn, "SELECT id_komponen, quantity_komponen FROM list_komponen WHERE id_product_finish = '$idProduct'");
                        while ($data = mysqli_fetch_assoc($selectkomp)) {
                            $idKomponen = $data['id_komponen'];
                            $qtykomp = $data['quantity_komponen'];
                            $qtytotal = $qty[$index] * $qtykomp;
                            $num = mysqli_query($conn, "SELECT count(*) AS total FROM request_gudang WHERE id_history = '$ids[$index]' AND jenis = 'cancel' AND id_product = '$idProduct'");
                            $numRows = mysqli_fetch_assoc($num)['total'];  // Ambil hasil count
                            if ($numRows == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO request_gudang (id_product, id_gudang, id_history, status, jenis, date, quantity_req) VALUES ('$idProduct', '$idKomponen', '$ids[$index]', 'requested', 'cancel', '$date', '$qtytotal')");
                                if ($insert) {
                                    echo 'Request Gudang berhasil.';
                                } else {
                                    echo 'Gagal menambahkan request gudang.';
                                }
                            } else {
                                echo 'sudah ada';
                            }
                        }
                        $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids[$index]'");
                    } elseif ($assoc['status_pick'] == 'pending' && $assoc['output'] == 'gudang') {
                        $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids[$index]'");
                        if ($update) {
                            $update2 = mysqli_query($conn, "UPDATE request_id SET status_req = 'cancel', invoice = '$invoices[$index] cancel' WHERE invoice = '$invoices[$index]' AND id_toko = '$idp[$index]'");
                            if ($update2) {
                                echo 'Update request_id berhasil.';
                            } else {
                                echo 'Gagal mengupdate request_id.';
                            }
                        }
                    } else {
                        $update = mysqli_query($conn, "UPDATE shop_id SET status_order = 'cancel', status_pick = 'cancel' WHERE id_shop = '$ids[$index]'");
                    }
                }
            }
        } else {
            // echo $qty[$index] . " tidak sama dengan " . $number[$index];
        }
    }
    $allDone = mysqli_query($conn, "SELECT COUNT(*) as count_done FROM shop_id WHERE invoice = '$invoices' AND status_pick = 'cancel'");
    $countDone = mysqli_fetch_assoc($allDone)['count_done'];
    $selectShopCount = mysqli_query($conn, "SELECT COUNT(*) as count_shop FROM shop_id WHERE invoice = '$invoices'");
    $countShop = mysqli_fetch_assoc($selectShopCount)['count_shop'];
    if ($countDone == $countShop) {
        mysqli_query($conn, "UPDATE tracking SET refaund = 'cancel', admin = 'cancel', picking = 'cancel', box = 'cancel', checking = 'cancel', dikurir = 'cancel', packing = 'cancel' WHERE invoice = '$invoices'");
        header('Location: ?url=invoice&sku=' . urlencode($invoices));
    }
}


if (isset($_POST['forwardPrepare'])) {
    $date = date('Y-m-d H:i:s');
    $id_request = $_POST['idRequest']; // Array of id_request
    $invoice = $_POST['inv']; // Array of invoices
    $sku = $_POST['sku_toko']; // Array of SKUs
    $qty = $_POST['qty']; // Array of quantities
    $nama = $_POST['nama']; // Array of names
    $penerima = $_POST['penerima'];
    $idToko = $_POST['idToko']; // Array of idToko
    $idProduct = $_POST['idProduct'];
    foreach ($id_request as $id_prepare) {
        // Looping berdasarkan jumlah elemen di dalam array `nama` untuk id_prepare tertentu
        foreach ($nama[$id_prepare] as $index => $currentNama) {
            $currentInvoice = $invoice[$id_prepare];
            $currentPenerima = $penerima[$id_prepare];
            $currentSku = $sku[$id_prepare][$index];
            $currentQty = $qty[$id_prepare][$index];
            $currentIdToko = $idToko[$id_prepare][$index];
            $currentIdProduct = $idProduct[$id_prepare][$index];
            $id_order = "$currentInvoice $currentNama $currentSku $currentIdToko";
            $selectkomp = mysqli_query($conn, "SELECT quantity_komponen FROM `list_komponen`
            INNER JOIN toko_id ON toko_id.id_product = list_komponen.id_product_finish
            WHERE id_toko = '$currentIdToko'
            GROUP BY quantity_komponen");
            if ($selectkomp) {
                $assockomp = mysqli_fetch_array($selectkomp);
                $quantitykomp = $assockomp['quantity_komponen'];
                $qtyAsli = $currentQty / $quantitykomp;
                $qtyAsli = ceil($qtyAsli);
                $select = mysqli_query($conn, "SELECT id_orderitem FROM shop_id WHERE id_orderitem = '$id_order'");
                $hitung = mysqli_num_rows($select);
                if ($hitung == 0) {
                    $insert = mysqli_query($conn, "INSERT INTO shop_id(id_orderitem,invoice,tanggal_bayar,nama_product,sku_toko,jumlah, penerima, kurir, resi, id_product, olshop) VALUES ('$id_order', '$currentInvoice', '$date', '$currentNama', '$currentSku', '$qtyAsli', '$currentPenerima', 'prepare', '$currentInvoice', '$currentIdToko', 'prepare')");
                    if ($insert) {
                        $update = mysqli_query($conn, "UPDATE prepare_total SET id_gudang = '$currentIdToko' WHERE id_product = '$currentIdProduct' AND id_prepare = '$id_prepare'");
                        if ($update) {
                            $exclude = mysqli_query($conn, "SELECT invoice FROM tracking WHERE invoice = '$currentInvoice'");
                            $excluderows = mysqli_num_rows($exclude);
                            if ($excluderows == 0) {
                                $inserttracking = "INSERT INTO tracking (time_load, invoice, no_resi, nama_kurir) VALUES ('$date','$currentInvoice', '$currentInvoice', 'produksi')";
                                if ($conn->query($inserttracking) !== TRUE) {
                                    echo "Error: " . $inserttracking . "<br>" . $conn->error;
                                }
                            }
                        }
                    }
                } else {
                    echo 'data sudah ada';
                }
            }
        }
    }
    $update = mysqli_query($conn, "UPDATE request_prepare SET status_prepare = 'pending pick' WHERE id_prepare = '$id_prepare'");
}

if (isset($_POST['forwardItem'])) {
    date_default_timezone_set('Asia/Jakarta');
    $current_date = date('Y-m-d H:i:s');
    $date = date('Y-m-d');
    $btn = $_POST['forwardItem'];
    $note = $_POST['noteForWorker'];
    $id_toko = $_POST['idToko'];
    $id_shop = $_POST['idShop'];
    $inv = $_POST['inv'];
    $qty = $_POST['qtyShop'];
    $option = $_POST['option'];
    foreach ($btn as $index => $id) {
        $select = mysqli_query($conn, "SELECT * FROM toko_prepare WHERE id_product = '$id_toko[$index]' AND status_send = 'unprocessed'");
        if (mysqli_num_rows($select) == 0) {
            $insert = mysqli_query($conn, "INSERT INTO toko_prepare (id_product, quantity_hasil, note, status_send, id_shop, tipe, date) VALUES ('$id_toko[$index]', '$qty[$index]', '$note[$index]', 'unprocessed', '$id_shop[$index]', '$option[$index]', '$current_date')");
            if ($insert) {
                echo $inv[$index];
                header('location:?url=resi&noresi=' . $inv[$index] . '');
            }
        } else {
            echo '
            <script>
            alert("Barang Sudah Di Request!")
            </script>
            ';
        }
    }
}

if (isset($_POST['buttonReject'])) {
    $buttonReject = $_POST['buttonReject'];
    foreach ($buttonReject as $requestId => $value) {
        $update = mysqli_query($conn, "UPDATE request_toko SET status_request = 'cancel' WHERE id_request = '$requestId'");
        if ($update) {
            echo "
            <script>
                alert('Request ID: $requestId has been cancelled');
            </script>
            ";
        }
    }
}

if (isset($_POST['hitungkomponen'])) {
    $idptotal = $_POST['idptotal'];
    $qtyhitung = $_POST['qtyhitung'];
    $idp = $_POST['idp'];
    $tipe = $_POST['tipe'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $option = $_POST['option'] ?? '';
    $note = $_POST['note'];
    $user = $_POST['user'];
    $salah = 0;
    foreach ($idptotal as $index => $idpt) {
        $jenis = 'toko';
        $qtyhitung_value = $qtyhitung[$index] ?? 0;
        $idpt = mysqli_real_escape_string($conn, $idpt);
        $select = mysqli_query($conn, "SELECT quantity_tambah, request_id.id_request, type_req FROM request_total
        INNER JOIN request_id ON request_id.id_request = request_total.id_request
        WHERE id_total = '$idpt'");
        if (!$select) {
            die('Query failed: ' . mysqli_error($conn));
        }
        $data = mysqli_fetch_assoc($select);
        $qtytotal = $data['quantity_tambah'];
        $idReq = $data['id_request'];
        $typeReq = $data['type_req'];
        $qtyTolerance = $qtytotal * 0.02;
        if ($option[$index] != 'Adjustment Packaging') {
            if ($qtytotal == $qtyhitung_value) {
                $success = mysqli_query($conn, "UPDATE checking_gudang SET status = 'approve', quantity = '$qtyhitung_value' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                if ($success) {
                    $update = mysqli_query($conn, "UPDATE request_total SET jenis_pack = 'repack', note = '$note[$index]' WHERE id_total = '$idpt'");
                    if ($update) {
                        $updatereqid = mysqli_query($conn, "UPDATE request_id 
                        SET status_req = 'On Process (Di Proses Toko)', 
                            quantity_count = request_id.quantity_req 
                        WHERE id_request = '$idReq'");
                    }
                }
            } else {
                $fail = mysqli_query($conn, "UPDATE checking_gudang SET status = 'failcheck', quantity = '$qtyhitung_value', fail = 'check' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                $salah += 1;
            }
        } else {
            if ($typeReq == 'refill') {
                if (abs($qtyhitung_value - $qtytotal) <= $qtyTolerance) {
                    $select = mysqli_query($conn, "SELECT * FROM (
                                                SELECT quantity_komponen 
                                                FROM request_total
                                                INNER JOIN request_id ON request_id.id_request = request_total.id_request
                                                INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                                                INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                                                INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                                                WHERE id_total = '$idpt'
                                                UNION ALL
                                                SELECT quantity_komponen 
                                                FROM request_total
                                                INNER JOIN request_id ON request_id.id_request = request_total.id_request
                                                INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                                                INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                                                INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                                                WHERE id_total = '$idpt'
                                            ) AS combined_result");
                    $fetch = mysqli_fetch_array($select);
                    $qtyKomp = $fetch['quantity_komponen'];
                    $qtyCount = $qtyhitung_value / $qtyKomp;
                    $success = mysqli_query($conn, "UPDATE checking_gudang SET status = 'approve', quantity = '$qtyhitung_value' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                    if ($success) {
                        $update = mysqli_query($conn, "UPDATE request_total SET jenis_pack = 'supplier', note = '$note[$index]' WHERE id_total = '$idpt'");
                        if ($update) {
                            $cekresi = "SELECT COUNT(*) as total FROM checking_gudang WHERE id_prepare = '$idp' AND status != 'approve' AND tipe = 'toko'";
                            $cekresihasil = mysqli_query($conn, $cekresi);
                            if (!$cekresihasil) {
                                die('Query failed: ' . mysqli_error($conn));
                            }
                            $resiData = mysqli_fetch_assoc($cekresihasil);
                            if ($resiData['total'] == 0) {
                                $updatereqid = mysqli_query($conn, "UPDATE request_id 
                            SET status_req = 'On Process (Di Proses Toko)', 
                                quantity_count = '$qtyCount',
                                date_checker = '$date'
                            WHERE id_request = '$idReq'");
                            }
                        }
                    }
                } else {
                    $fail = mysqli_query($conn, "UPDATE checking_gudang SET status = 'failcheck', quantity = '$qtyhitung_value', fail = 'check' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                    echo '<script>
                    alert("Diluar Toleransi!");
                    </script>';
                    $salah += 1;
                }
            } else {
                if ($qtytotal == $qtyhitung_value) {
                    $success = mysqli_query($conn, "UPDATE checking_gudang SET status = 'approve', quantity = '$qtyhitung_value' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                    if ($success) {
                        $update = mysqli_query($conn, "UPDATE request_total SET jenis_pack = 'supplier' WHERE id_total = '$idpt'");
                        if ($update) {
                            $updatereqid = mysqli_query($conn, "UPDATE request_id 
                            SET status_req = 'On Process (Di Proses Toko)', 
                                quantity_count = request_id.quantity_req,
                                date_checker = '$date'
                            WHERE id_request = '$idReq'");
                        }
                    }
                } else {
                    $fail = mysqli_query($conn, "UPDATE checking_gudang SET status = 'failcheck', quantity = '$qtyhitung_value', fail = 'check' WHERE id_prepare_total = '$idpt' AND tipe = '$jenis'");
                    $salah += 1;
                }
            }
        }
        if ($salah == 0) {
            echo '<script>
        alert("Request dengan id ' . htmlspecialchars($idp) . ' sudah bisa lanjut ke proses selanjutnya");
        window.location.href = "?url=qrapprove";
        </script>';
        } else {
            echo '<script>
        alert("QUANTITY YANG DI INPUT TIDAK SESUAI, TOLONG CEK KEMBALI!");
        window.location.href = "?url=comparetoko&idp=' . $idp . '";
        </script>';
        }
    }
}

if (isset($_POST['reqPickup'])) {
    $inv = $_POST['inv'];
    $update = mysqli_query($conn, "UPDATE tracking SET status_mp = 'Menunggu Pickup' WHERE invoice = '$inv'");
    if ($update) {
        echo '<script>
        alert("Invoice Ini ' . $inv . ' Sudah Di Request Pickup");
      </script>';
    }
}

if (isset($_POST['forwardWorkerPrepare'])) {
    $check = $_POST['id_request'];
    $user = $_POST['user'];
    foreach ($check as $x => $i) {
        // echo $user[$i];
        // echo '!!';
        // echo $i;
        $update = mysqli_query($conn, "UPDATE toko_prepare SET id_user = '$user[$i]', status_send = 'approved' WHERE id_toko_prepare = '$i'");
    }
}

if (isset($_POST['addslamentah'])) {
    $sku = $_POST['sku'];
    $waktu = $_POST['waktu'];
    $qty = $_POST['qty'];
    $select = mysqli_query($conn, "SELECT id_product FROM toko_id WHERE sku_toko = '$sku'");
    if (mysqli_num_rows($select) == 0) {
        echo "<script>
        alert('SKU Toko tidak ada di barang mateng')
        </script>";
    } else {
        $data = mysqli_fetch_array($select);
        $idp = $data['id_product'];
        $ambil = mysqli_query($conn, "SELECT id_product FROM sla_prepare_mentah WHERE id_product = '$idp'");
        $hitung = mysqli_num_rows($ambil);
        if ($hitung == 1) {
            echo "<script>
            alert('SKU Gudang sudah ada di SLA barang mentah')
            </script>";
        } else {
            $insert = mysqli_query($conn, "INSERT INTO sla_prepare_mentah(deadline, id_product, qty) VALUES ('$waktu', '$idp', '$qty')");
        }
    }
}

if (isset($_POST['slaggudangmentah'])) {
    $waktu = $_POST['waktu'];
    $ids = $_POST['ids'];
    $qty = $_POST['qty'];
    $update = mysqli_query($conn, "UPDATE sla_prepare_mentah SET deadline = '$waktu', qty = '$qty' WHERE id_sla = '$ids'");
}
// kasih tugas ke prepare
if (isset($_POST['addprepare'])) {
    $id_request = $_POST['id_request'];
    $note = $_POST['note'];
    $quantity = $_POST['quantity'];
    $id_user = $_POST['id_user'];
    foreach ($id_user as $user_id) {
        $user_note = mysqli_real_escape_string($conn, $note[$user_id]);
        $uniq = $id_request . "_" . $user_id;
        $cekdata = mysqli_query($conn, "SELECT id_prepare_toko FROM prepare_toko WHERE uniq_id = '$uniq'");
        $hitung  = mysqli_num_rows($cekdata);
        if ($hitung == 0) {
            $insert = mysqli_query($conn, "INSERT INTO prepare_toko (uniq_id,id_request, id_user, note_worker, status_worker,quantity) VALUES ('$uniq','$id_request', '$user_id', '$user_note', 'unprocessed','$quantity[$user_id]')");
        } else {
            $update = mysqli_query($conn, "UPDATE prepare_toko SET id_user = '$user_id' WHERE uniq_id = '$uniq'");
        }
    }
    header('location:?url=sendtaskdetail&id_request=' . $id_request);
}


if (isset($_POST['submitOnduty'])) {
    $button = $_POST['submitOnduty'];
    $tipe = $_POST['tipe'];
    $inputqty = $_POST['inputqty'];
    $typereq = $_POST['typereq'];
    $qtyreq = $_POST['qtyreq'];
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $user = $_POST['user'];
    foreach ($button as $requestId => $value) {
        $tipeValue = $tipe[$requestId];
        $inputValue = $inputqty[$requestId];
        $typeValue = $typereq[$requestId];
        $qtyValue = $qtyreq[$requestId];
        if ($typeValue == 'refill') {
            $select = mysqli_query($conn, "SELECT request_id.id_toko, quantity_toko, status_req, quantity_count, on_duty FROM request_id
            INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko WHERE id_request = '$requestId'");
            $data = mysqli_fetch_array($select);
            $idToko = $data['id_toko'];
            $qtyToko = $data['quantity_toko'];
            $statusReq = $data['status_req'];
            $countValue = $data['quantity_count'];
            $on_duty = $data['on_duty'];
            $stok = $qtyToko + $qtyValue;
            if ($tipeValue ==  'Barang Tidak di Prepare') {
                if ($statusReq == 'On Process (Di Proses Toko)') {
                    if ($countValue == $inputValue) {
                        if ($countValue == $qtyValue) {
                            $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                            if (mysqli_num_rows($selecttoko) == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stok','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                if ($insert) {
                                    $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stok' WHERE id_toko = '$idToko'");
                                    if ($update) {
                                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', on_duty = '$user', quantity_check = '$inputValue', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($updatereq) {
                                            $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId'");
                                        }
                                    }
                                }
                            } else {
                                echo 'data sudah ada';
                            }
                        } else {
                            $qtyAdjust = $qtyValue - $countValue;
                            $qtyCase = $countValue - $qtyValue;
                            $selectData = mysqli_query($conn, "SELECT * FROM (
                             SELECT 
                             request_total.id_gudang, 
                             quantity_komponen, 
                             mateng_id.quantity AS qty_gudang, 
                             toko_id.quantity_toko, 
                             request_id.id_toko, 
                             request_total.id_total,
                             quantity_tambah,
                             request_total.note,
                             id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                             UNION ALL
                             SELECT 
                             request_total.id_gudang, 
                             quantity_komponen, 
                             gudang_id.quantity AS qty_gudang, 
                             toko_id.quantity_toko, 
                             request_id.id_toko, 
                             request_total.id_total,
                             quantity_tambah,
                             request_total.note,
                             id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                             ) AS combined
                             GROUP BY id_total");
                            while ($fetch = mysqli_fetch_array($selectData)) {
                                $idGudang = $fetch['id_gudang'];
                                $qtyKomp = $fetch['quantity_komponen'];
                                $idToko = $fetch['id_toko'];
                                $qtyTotal = $fetch['quantity_tambah'];
                                $qtyToko = $fetch['quantity_toko'];
                                $qtyGudang = $fetch['qty_gudang'];
                                $idKomponen = $fetch['id_komponen'];
                                $note = $fetch['note'];
                                $qtyKali = $qtyAdjust * $qtyKomp;
                                $qtyCaseKali = $qtyCase * $qtyKomp;
                                $qtyAkhir = $qtyGudang + $qtyKali;
                                $case = mysqli_query($conn, "INSERT INTO case_management(id_product, jenis_case, detail, tanggal, lokasi, proses, quantity, quantity_check) VALUES ('$idKomponen', 'quantity pack kurang', '$note', '$date', 'toko', 'refill', '$qtyCaseKali', '$qtyTotal')");
                                if ($case) {
                                    $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId' AND id_gudang = '$idGudang'");
                                    if ($updateTotal) {
                                        $update2 = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', quantity_check = '$inputValue', on_duty = '$user', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($update2) {
                                            $stokAkhir = $qtyToko + $qtyValue;
                                            $stokAkhir2 = $stokAkhir + $qtyCase;
                                            if ($typeValue == 'refill') {
                                                $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                                                if (mysqli_num_rows($selecttoko) == 0) {
                                                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stokAkhir','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                                    if ($insert) {
                                                        $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date, note_transaksi) VALUES('$requestId $idToko $typeValue Adj','$stokAkhir','$stokAkhir2','Adjustment Packaging','$qtyCase','$idToko','$requestId','$user','$date', '$note')");
                                                        if ($insert2) {
                                                            $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stokAkhir2' WHERE id_toko = '$idToko'");
                                                        }
                                                    }
                                                } else {
                                                    echo 'data sudah ada';
                                                }
                                            } else {
                                                echo 'dia adalah request';
                                            }
                                        }
                                    }
                                }
                                //     }
                                // }
                            }
                        }
                    } else {
                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Error', on_duty = '$user', quantity_check = '$inputValue' WHERE id_request = '$requestId'");
                    }
                } else {
                    if ($on_duty !== $user) {
                        $option = $_POST['option'];
                        if ($inputValue != $qtyValue) {
                            $qtyAdjust = $qtyValue - $inputValue;
                            $qtyCase = $inputValue - $qtyValue;
                            $selectData = mysqli_query($conn, "SELECT * FROM (
                        SELECT 
                         request_total.id_gudang, 
                         quantity_komponen, 
                         mateng_id.quantity AS qty_gudang, 
                         toko_id.quantity_toko, 
                         request_id.id_toko, 
                         request_total.id_total,
                         quantity_tambah,
                         request_total.note,
                         id_komponen
                     FROM request_total 
                     INNER JOIN request_id ON request_id.id_request = request_total.id_request
                     INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                     INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                     INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                     WHERE request_id.id_request = '$requestId'
                     UNION ALL
                     SELECT 
                         request_total.id_gudang, 
                         quantity_komponen, 
                         gudang_id.quantity AS qty_gudang, 
                         toko_id.quantity_toko, 
                         request_id.id_toko, 
                         request_total.id_total,
                         quantity_tambah,
                         request_total.note,
                         id_komponen
                     FROM request_total 
                     INNER JOIN request_id ON request_id.id_request = request_total.id_request
                     INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                     INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                     INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                     WHERE request_id.id_request = '$requestId'
                        ) AS combined
                        GROUP BY id_total");
                            while ($fetch = mysqli_fetch_array($selectData)) {
                                $idGudang = $fetch['id_gudang'];
                                $qtyKomp = $fetch['quantity_komponen'];
                                $idToko = $fetch['id_toko'];
                                $qtyTotal = $fetch['quantity_tambah'];
                                $qtyToko = $fetch['quantity_toko'];
                                $qtyGudang = $fetch['qty_gudang'];
                                $idKomponen = $fetch['id_komponen'];
                                $qtyKali = $qtyAdjust * $qtyKomp;
                                $qtyCaseKali = $qtyCase * $qtyKomp;
                                $qtyAkhir = $qtyGudang + $qtyKali;
                                $note = $fetch['note'];
                                $qtyAdjust = $qtyValue - $countValue;
                                $qtyCase = $inputValue - $qtyValue;
                                $selectData = mysqli_query($conn, "SELECT * FROM (
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 mateng_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                             UNION ALL
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 gudang_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                         ) AS combined
                         GROUP BY id_total");
                                while ($fetch = mysqli_fetch_array($selectData)) {
                                    $idGudang = $fetch['id_gudang'];
                                    $qtyKomp = $fetch['quantity_komponen'];
                                    $idToko = $fetch['id_toko'];
                                    $qtyTotal = $fetch['quantity_tambah'];
                                    $qtyToko = $fetch['quantity_toko'];
                                    $qtyGudang = $fetch['qty_gudang'];
                                    $idKomponen = $fetch['id_komponen'];
                                    $note = $fetch['note'];
                                    $qtyKali = $qtyAdjust * $qtyKomp;
                                    $qtyCaseKali = $qtyCase * $qtyKomp;
                                    $qtyAkhir = $qtyGudang + $qtyKali;
                                    $case = mysqli_query($conn, "INSERT INTO case_management(id_product, jenis_case, detail, tanggal, lokasi, proses, quantity, quantity_check) VALUES ('$idKomponen', 'quantity pack kurang', '$note', '$date', 'toko', 'refill', '$qtyCaseKali', '$qtyTotal')");
                                    if ($case) {
                                        $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId' AND id_gudang = '$idGudang'");
                                        if ($updateTotal) {
                                            $update2 = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', failure = '$option[$requestId]', date_acc = '$date' WHERE id_request = '$requestId'");
                                            if ($update2) {
                                                $stokAkhir = $qtyToko + $qtyValue;
                                                $stokAkhir2 = $stokAkhir + $qtyCase;
                                                if ($typeValue == 'refill') {
                                                    $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                                                    if (mysqli_num_rows($selecttoko) == 0) {
                                                        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stokAkhir','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                                        if ($insert) {
                                                            $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date, note_transaksi) VALUES('$requestId $idToko $typeValue Adj','$stokAkhir','$stokAkhir2','Adjustment Packaging','$qtyCase','$idToko','$requestId','$user','$date', '$note')");
                                                            if ($insert2) {
                                                                $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stokAkhir2' WHERE id_toko = '$idToko'");
                                                            }
                                                        }
                                                    } else {
                                                        echo 'data sudah ada';
                                                    }
                                                } else {
                                                    echo 'dia adalah request';
                                                }
                                            }
                                        }
                                    }
                                    //     }
                                    // }
                                }
                            }
                        } else {
                            $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                            if (mysqli_num_rows($selecttoko) == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stok','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                if ($insert) {
                                    $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stok' WHERE id_toko = '$idToko'");
                                    if ($update) {
                                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', failure = '$option[$requestId]', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($updatereq) {
                                            $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId'");
                                        }
                                    }
                                }
                            } else {
                                echo 'data sudah ada';
                            }
                        }
                    }
                }
            } else {
                if ($statusReq == 'On Process (Di Proses Toko)') {
                    if ($inputValue == $countValue) {
                        if ($countValue == $qtyValue) {
                            $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                            if (mysqli_num_rows($selecttoko) == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stok','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                if ($insert) {
                                    $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stok' WHERE id_toko = '$idToko'");
                                    if ($update) {
                                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', on_duty = '$user', quantity_check = '$inputValue', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($updatereq) {
                                            $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId'");
                                        }
                                    }
                                }
                            } else {
                                echo 'data sudah ada';
                            }
                        } else {
                            $qtyAdjust = $qtyValue - $countValue;
                            $qtyCase = $countValue - $qtyValue;
                            $selectData = mysqli_query($conn, "SELECT * FROM (
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 mateng_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                             UNION ALL
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 gudang_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                         ) AS combined
                         GROUP BY id_total");
                            while ($fetch = mysqli_fetch_array($selectData)) {
                                $idGudang = $fetch['id_gudang'];
                                $qtyKomp = $fetch['quantity_komponen'];
                                $idToko = $fetch['id_toko'];
                                $qtyTotal = $fetch['quantity_tambah'];
                                $qtyToko = $fetch['quantity_toko'];
                                $qtyGudang = $fetch['qty_gudang'];
                                $idKomponen = $fetch['id_komponen'];
                                $note = $fetch['note'];
                                $qtyKali = $qtyAdjust * $qtyKomp;
                                $qtyCaseKali = $qtyCase * $qtyKomp;
                                $qtyAkhir = $qtyGudang + $qtyKali;
                                $case = mysqli_query($conn, "INSERT INTO case_management(id_product, jenis_case, detail, tanggal, lokasi, proses, quantity, quantity_check) VALUES ('$idKomponen', 'quantity pack kurang', '$note', '$date', 'toko', 'refill', '$qtyCaseKali', '$qtyTotal')");
                                if ($case) {
                                    $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId' AND id_gudang = '$idGudang'");
                                    if ($updateTotal) {
                                        $update2 = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', quantity_check = '$inputValue', on_duty = '$user', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($update2) {
                                            $stokAkhir = $qtyToko + $qtyValue;
                                            $stokAkhir2 = $stokAkhir + $qtyCase;
                                            if ($typeValue == 'refill') {
                                                $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                                                if (mysqli_num_rows($selecttoko) == 0) {
                                                    $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stokAkhir','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                                    if ($insert) {
                                                        $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date, note_transaksi) VALUES('$requestId $idToko $typeValue Adj','$stokAkhir','$stokAkhir2','Adjustment Packaging','$qtyCase','$idToko','$requestId','$user','$date', '$note')");
                                                        if ($insert2) {
                                                            $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stokAkhir2' WHERE id_toko = '$idToko'");
                                                        }
                                                    }
                                                } else {
                                                    echo 'data sudah ada';
                                                }
                                            } else {
                                                echo 'dia adalah request';
                                            }
                                        }
                                    }
                                }
                                //     }
                                // }
                            }
                        }
                    } else {
                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Error', on_duty = '$user', quantity_check = '$inputValue' WHERE id_request = '$requestId'");
                    }
                } else {
                    if ($on_duty !== $user) {
                        $option = $_POST['option'];
                        if ($inputValue != $qtyValue) {
                            $qtyAdjust = $qtyValue - $inputValue;
                            $qtyCase = $inputValue - $qtyValue;
                            $selectData = mysqli_query($conn, "SELECT * FROM (
                        SELECT 
                         request_total.id_gudang, 
                         quantity_komponen, 
                         mateng_id.quantity AS qty_gudang, 
                         toko_id.quantity_toko, 
                         request_id.id_toko, 
                         request_total.id_total,
                         quantity_tambah,
                         request_total.note,
                         id_komponen
                     FROM request_total 
                     INNER JOIN request_id ON request_id.id_request = request_total.id_request
                     INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                     INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                     INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                     WHERE request_id.id_request = '$requestId'
                     UNION ALL
                     SELECT 
                         request_total.id_gudang, 
                         quantity_komponen, 
                         gudang_id.quantity AS qty_gudang, 
                         toko_id.quantity_toko, 
                         request_id.id_toko, 
                         request_total.id_total,
                         quantity_tambah,
                         request_total.note,
                         id_komponen
                     FROM request_total 
                     INNER JOIN request_id ON request_id.id_request = request_total.id_request
                     INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                     INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                     INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                     WHERE request_id.id_request = '$requestId'
                        ) AS combined
                        GROUP BY id_total");
                            while ($fetch = mysqli_fetch_array($selectData)) {
                                $idGudang = $fetch['id_gudang'];
                                $qtyKomp = $fetch['quantity_komponen'];
                                $idToko = $fetch['id_toko'];
                                $qtyTotal = $fetch['quantity_tambah'];
                                $qtyToko = $fetch['quantity_toko'];
                                $qtyGudang = $fetch['qty_gudang'];
                                $idKomponen = $fetch['id_komponen'];
                                $qtyKali = $qtyAdjust * $qtyKomp;
                                $qtyCaseKali = $qtyCase * $qtyKomp;
                                $qtyAkhir = $qtyGudang + $qtyKali;
                                $note = $fetch['note'];
                                $qtyAdjust = $qtyValue - $countValue;
                                $qtyCase = $inputValue - $qtyValue;
                                $selectData = mysqli_query($conn, "SELECT * FROM (
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 mateng_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN mateng_id ON mateng_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                             UNION ALL
                             SELECT 
                                 request_total.id_gudang, 
                                 quantity_komponen, 
                                 gudang_id.quantity AS qty_gudang, 
                                 toko_id.quantity_toko, 
                                 request_id.id_toko, 
                                 request_total.id_total,
                                 quantity_tambah,
                                 request_total.note,
                                 id_komponen
                             FROM request_total 
                             INNER JOIN request_id ON request_id.id_request = request_total.id_request
                             INNER JOIN gudang_id ON gudang_id.id_gudang = request_total.id_gudang
                             INNER JOIN toko_id ON toko_id.id_toko = request_id.id_toko
                             INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                             WHERE request_id.id_request = '$requestId'
                         ) AS combined
                         GROUP BY id_total");
                                while ($fetch = mysqli_fetch_array($selectData)) {
                                    $idGudang = $fetch['id_gudang'];
                                    $qtyKomp = $fetch['quantity_komponen'];
                                    $idToko = $fetch['id_toko'];
                                    $qtyTotal = $fetch['quantity_tambah'];
                                    $qtyToko = $fetch['quantity_toko'];
                                    $qtyGudang = $fetch['qty_gudang'];
                                    $idKomponen = $fetch['id_komponen'];
                                    $note = $fetch['note'];
                                    $qtyKali = $qtyAdjust * $qtyKomp;
                                    $qtyCaseKali = $qtyCase * $qtyKomp;
                                    $qtyAkhir = $qtyGudang + $qtyKali;
                                    $case = mysqli_query($conn, "INSERT INTO case_management(id_product, jenis_case, detail, tanggal, lokasi, proses, quantity, quantity_check) VALUES ('$idKomponen', 'quantity pack kurang', '$note', '$date', 'toko', 'refill', '$qtyCaseKali', '$qtyTotal')");
                                    if ($case) {
                                        $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId' AND id_gudang = '$idGudang'");
                                        if ($updateTotal) {
                                            $update2 = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', failure = '$option[$requestId]', date_acc = '$date' WHERE id_request = '$requestId'");
                                            if ($update2) {
                                                $stokAkhir = $qtyToko + $qtyValue;
                                                $stokAkhir2 = $stokAkhir + $qtyCase;
                                                if ($typeValue == 'refill') {
                                                    $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                                                    if (mysqli_num_rows($selecttoko) == 0) {
                                                        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stokAkhir','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                                        if ($insert) {
                                                            $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date, note_transaksi) VALUES('$requestId $idToko $typeValue Adj','$stokAkhir','$stokAkhir2','Adjustment Packaging','$qtyCase','$idToko','$requestId','$user','$date', '$note')");
                                                            if ($insert2) {
                                                                $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stokAkhir2' WHERE id_toko = '$idToko'");
                                                            }
                                                        }
                                                    } else {
                                                        echo 'data sudah ada';
                                                    }
                                                } else {
                                                    echo 'dia adalah request';
                                                }
                                            }
                                        }
                                    }
                                    //     }
                                    // }
                                }
                            }
                        } else {
                            $selecttoko = mysqli_query($conn, "SELECT * FROM transaksi_toko WHERE uniq_transaksi = '$requestId $idToko $typeValue'");
                            if (mysqli_num_rows($selecttoko) == 0) {
                                $insert = mysqli_query($conn, "INSERT INTO transaksi_toko(uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, id_history, nama_user, date) VALUES('$requestId $idToko $typeValue','$qtyToko','$stok','refill','$qtyValue','$idToko','$requestId','$user','$date')");
                                if ($insert) {
                                    $update = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$stok' WHERE id_toko = '$idToko'");
                                    if ($update) {
                                        $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', failure = '$option[$requestId]', date_acc = '$date' WHERE id_request = '$requestId'");
                                        if ($updatereq) {
                                            $updateTotal = mysqli_query($conn, "UPDATE request_total SET status_total = 'Approved' WHERE id_request = '$requestId'");
                                        }
                                    }
                                }
                            } else {
                                echo 'data sudah ada';
                            }
                        }
                    }
                }
            }
        } else {
            if ($qtyValue == $inputValue) {
                $updatereq = mysqli_query($conn, "UPDATE request_id SET status_req = 'Approved', on_duty = '$user', date_acc = '$date' WHERE id_request = '$requestId'");
            } else {
                echo 'quantity berbeda';
            }
        }
    }
    header('Location: ?url=sendtaskrefill');
}

if (isset($_POST['pythonTokped'])) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Tentukan direktori untuk menyimpan file yang diunggah
        $uploadDir = __DIR__ . '/uploads/';

        // Pastikan folder upload ada, jika tidak maka buat folder tersebut
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Tentukan jalur file yang akan disimpan
        $filePath = $uploadDir . basename($_FILES['file']['name']);

        // Pindahkan file yang diunggah ke lokasi tujuan
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            // Ubah izin file agar bisa diakses oleh semua pengguna
            chmod($filePath, 0777);

            // Tentukan jalur skrip Python dan log
            $pythonPath = '/usr/bin/python3';  // Pastikan ini adalah jalur Python yang benar
            $scriptPath = '/home/mirorim/inventory2/mobileapps/scrapping/test2.py';  // Ganti sesuai jalur skrip Python
            $logFile = $uploadDir . 'python_debug.log';

            // Debugging tambahan: cek Python path dan environment
            $debugCommand = $pythonPath . " -c \"import sys; print(sys.executable, sys.path)\"";
            echo "Debug Python Path:<br>";
            echo nl2br(shell_exec($debugCommand));

            // Buat perintah untuk menjalankan skrip Python
            $command = $pythonPath . " " . escapeshellarg($scriptPath) . " --file " . escapeshellarg($filePath) .
                " > " . escapeshellarg($logFile) . " 2>&1";

            // Eksekusi perintah
            $output = shell_exec($command);

            // Debugging output
            if ($output === null) {
                echo "Error: Tidak dapat menjalankan skrip Python.<br>";
                if (file_exists($logFile)) {
                    echo "Debug output:<br>";
                    echo nl2br(htmlspecialchars(file_get_contents($logFile), ENT_QUOTES, 'UTF-8'));
                } else {
                    echo "Log file tidak ditemukan.<br>";
                }
            } else {
                echo "Skrip Python berhasil dijalankan.<br>";
                echo "Output:<br>";
                echo "<pre>" . htmlspecialchars($output, ENT_QUOTES, 'UTF-8') . "</pre>";
                echo "Debug log:<br>";
                echo nl2br(htmlspecialchars(file_get_contents($logFile), ENT_QUOTES, 'UTF-8'));
            }
        } else {
            echo "Error: Gagal menyimpan file.";
        }
    } else {
        echo "Error: Tidak ada file yang diunggah atau terjadi kesalahan.";
    }
    header('location:?url=kelompok');
}


if (isset($_POST['updateShop'])) {
    $select = mysqli_query($conn, "SELECT invoice, resi, alamat, kurir, info FROM buyer_id WHERE date(date) = CURDATE()");
    $updateCount = 0;

    while ($data = mysqli_fetch_array($select)) {
        $invoice = $data['invoice'];
        $resi = $data['resi'];
        $alamat = $data['alamat'];
        $kurir = $data['kurir'];
        $info = $data['info'];

        if ($kurir == "Kurir Rekomendasi - Reguler") {
            if (strpos($alamat, '*') !== false) {
                $updateshop = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi' WHERE invoice = '$invoice'");
                if ($updateshop) {
                    if (preg_match("/Dipickup oleh (.+)/", $info, $matches)) {
                        $kurirfinal = strtolower(trim($matches[1]));
                    }
                    $trackingshop = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi', nama_kurir = '$kurirfinal' WHERE invoice = '$invoice'");
                }
            } else {
                if (preg_match("/^(.*?)\s*\(\d{10,}\)/", $alamat, $matches)) {
                    $nama = trim($matches[1]);
                }
                $nama = mysqli_real_escape_string($conn, $nama);
                $updateshop = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi', penerima = '$nama' WHERE invoice = '$invoice'");
                if ($updateshop) {
                    if (preg_match("/Dipickup oleh (.+)/", $info, $matches)) {
                        $kurirfinal = strtolower(trim($matches[1]));
                    }
                    $trackingshop = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi', nama_kurir = '$kurirfinal' WHERE invoice = '$invoice'");
                }
            }
        } else {
            if (strpos($alamat, '*') !== false) {
                $updateshop = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi' WHERE invoice = '$invoice'");
                if ($updateshop) {
                    $trackingshop = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi' WHERE invoice = '$invoice'");
                }
            } else {
                if (preg_match("/^(.*?)\s*\(\d{10,}\)/", $alamat, $matches)) {
                    $nama = trim($matches[1]);
                }
                $nama = mysqli_real_escape_string($conn, $nama);
                $updateshop = mysqli_query($conn, "UPDATE shop_id SET resi = '$resi', penerima = '$nama' WHERE invoice = '$invoice'");
                if ($updateshop) {
                    $trackingshop = mysqli_query($conn, "UPDATE tracking SET no_resi = '$resi' WHERE invoice = '$invoice'");
                }
            }
        }

        // Tambah counter setiap data berhasil di-update
        $updateCount++;
    }

    echo '
    <script>
    alert("Data Sudah Di Update! Total ' . $updateCount . ' data berhasil di-update.");
    window.location.href="?url=kelompok";
    </script>';
}


if (isset($_POST['mutasiitem'])) {
    $sku = $_POST['sku']; // Array SKU asal
    $sku2 = $_POST['sku2']; // Array SKU tujuan
    $qty1 = $_POST['qty']; // Array jumlah yang dikurangi dari SKU asal
    $qty2 = $_POST['qty2']; // Array jumlah yang ditambahkan ke SKU tujuan
    $user = $_POST['user']; // User yang melakukan transaksi
    $date = date('Y-m-d H:i:s');
    $count = count($sku);

    for ($i = 0; $i < $count; $i++) {
        // Ambil stok awal SKU asal
        $select = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko = '{$sku[$i]}'");
        $data = mysqli_fetch_array($select);
        $qtytoko1 = $data['quantity_toko'];

        // Kurangi stok SKU asal
        $kurang = $qtytoko1 - $qty1[$i];

        // Simpan transaksi pengurangan stok dari SKU asal
        $insert = mysqli_query($conn, "INSERT INTO transaksi_toko 
            (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, nama_user, date) 
            VALUES ('{$sku[$i]} $date mutasi item', '$qtytoko1', '$kurang', 'mutasi item', '{$qty1[$i]}', '{$sku[$i]}', '$user', '$date')");

        if ($insert) {
            // Update stok SKU asal
            $updatetoko = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$kurang' WHERE id_toko = '{$sku[$i]}'");

            if ($updatetoko) {
                // Ambil stok awal SKU tujuan
                $select2 = mysqli_query($conn, "SELECT quantity_toko FROM toko_id WHERE id_toko = '{$sku2[$i]}'");
                $data2 = mysqli_fetch_array($select2);
                $qtytoko2 = $data2['quantity_toko'];

                // Tambahkan stok ke SKU tujuan
                $tambah = $qtytoko2 + $qty2[$i];

                // Simpan transaksi penambahan stok ke SKU tujuan
                $insert2 = mysqli_query($conn, "INSERT INTO transaksi_toko 
                    (uniq_transaksi, stok_awal, stok_akhir, jenis_transaksi, quantity, id_toko, nama_user, date) 
                    VALUES ('{$sku2[$i]} $date mutasi item', '$qtytoko2', '$tambah', 'mutasi item', '{$qty2[$i]}', '{$sku2[$i]}', '$user', '$date')");

                if ($insert2) {
                    // Update stok SKU tujuan
                    $updatetoko2 = mysqli_query($conn, "UPDATE toko_id SET quantity_toko = '$tambah' WHERE id_toko = '{$sku2[$i]}'");
                }
            }
        }
    }
    header('location:?url=transaksi');
}

if (isset($_POST["load1"])) {
    // Load Excel file
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $stok_per_sku = []; // Menyimpan stok terakhir per SKU
    foreach ($data as $row) {
        $invoice = $row[1];
        $pembayaran = $row[2];
        $status = $row[3];
        $nama_product = $row[8];
        $string = strlen($nama_product);
        $varian = substr($nama_product, $string - 20, 20);
        $sku_toko = strval($row[10]);
        $sku_toko2 = explode('-', $sku_toko)[0]; // Ambil hanya bagian pertama sebelum "-"
        if (preg_match('/Pack-(\d+)/', $sku_toko, $matches)) {
            $jumlah_dibeli = $row[13] * intval($matches[1]);
        } else {
            $jumlah_dibeli = intval($row[13]);
        }
        $penerima = $row[28];
        $kurir = $row[33];
        $tipe = $row[34];
        $tanggal_kirim = $row[36];
        $waktu_kirim = $row[37];
        $tgl_kirim = "$tanggal_kirim $waktu_kirim";
        $tanggal_kirim3 = date('Y-m-d H:i:s', strtotime(str_replace('/', ' ', $tgl_kirim)));
        if (strpos($invoice, 'INV') === 0) {
            if (strtotime($pembayaran) !== false) {
                $tanggal_bayar = (new DateTime($pembayaran))->format('Y-m-d H:i:s');
                // Ambil id_toko sesuai dengan sku_toko
                $stmt = $conn->prepare("SELECT id_product, id_toko, sku_toko FROM toko_id WHERE sku_toko = ?");
                $stmt->bind_param("s", $sku_toko2);
                $stmt->execute();
                $dataselect = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $id_toko = $dataselect ? $dataselect['id_toko'] : '0';
                if ($status === 'Pesanan Baru') {
                    $id_orderitem = "$invoice $sku_toko $varian";
                    $stmt = $conn->prepare("SELECT * FROM shop_id WHERE sku_toko = ? AND invoice = ?");
                    $stmt->bind_param("ss", $sku_toko, $invoice);
                    $stmt->execute();
                    $stmt->store_result();
                    $cancel = $stmt->num_rows;
                    $stmt->close();
                    if ($cancel == 0) {
                        $select = mysqli_query($conn, "SELECT * FROM pre_order_stock_check WHERE sku_toko = '$sku_toko' AND invoice = '$invoice'");
                        if (mysqli_num_rows($select) == 0) {
                            // Hitung stok awal hanya jika SKU belum ada di array stok_per_sku
                            if (!isset($stok_per_sku[$sku_toko2])) {
                                $select2 = mysqli_query($conn, "WITH stok_gudang AS (
                                        SELECT SUM(quantity) AS qty_gudang, id_product FROM gudang_id
                                        GROUP BY id_product
                                        UNION ALL
                                        SELECT SUM(quantity) AS qty_gudang, id_product FROM mateng_id
                                        GROUP BY id_product
                                    )
                                    SELECT 
                                        toko_id.quantity_toko AS quantity_toko,
                                        COALESCE(stok_gudang.qty_gudang, 0) AS qty_gudang,
                                        COALESCE(stok_gudang.qty_gudang, 0) + quantity_toko AS total
                                    FROM toko_id
                                    INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                                    INNER JOIN stok_gudang ON stok_gudang.id_product = list_komponen.id_komponen
                                    WHERE toko_id.sku_toko = '$sku_toko2'
                                    GROUP BY toko_id.id_product
                                ");
                                $fetch2 = mysqli_fetch_array($select2);
                                $stok_per_sku[$sku_toko2] = $fetch2['total']; // Simpan stok awal
                            }
                            // Stok awal adalah stok sebelum dikurangi jumlah pesanan
                            $stok_awal = $stok_per_sku[$sku_toko2];
                            // Kurangi stok berdasarkan jumlah_dibeli
                            $stok_akhir = $stok_awal - $jumlah_dibeli;
                            // Simpan stok terbaru dalam array untuk iterasi berikutnya
                            $stok_per_sku[$sku_toko2] = $stok_akhir;
                            $olshop = 'Tokopedia';
                            // Insert ke pre_order_stock_check
                            $stmt = $conn->prepare("INSERT INTO pre_order_stock_check 
                                (id_orderitem, invoice, tanggal_bayar, nama_product, sku_toko, jumlah, id_toko, olshop, date_load, stok_akhir, stok_awal) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssssssss", 
                                $id_orderitem, $invoice, $tanggal_bayar, $nama_product, 
                                $sku_toko, $jumlah_dibeli, $id_toko, $olshop, $date, $stok_akhir, $stok_awal);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
    header('location:?url=preorder');
}

if (isset($_POST["load2"])) {
    // Load Excel file
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $stok_per_sku = []; // Menyimpan stok terakhir per SKU
    foreach (array_slice($data, 2) as $row) { // Lewati 2 baris pertama, mulai dari baris ke-3
        $invoice = $row[0];
        $pembayaran = $row[2];
        $status = $row[3];
        $resi = $row[37] ?: '';
        $tgl_bayar = $row[27];
        $tanggal_bayar = !empty($tgl_bayar) ? DateTime::createFromFormat('d/m/Y H:i:s', $tgl_bayar)->format('Y-m-d H:i:s') : '';
        $status1 = $row[1];
        $status2 = $row[2];
        $status3 = $row[3];
        $statusMp = $status1 . ' ' . $status2 . ($status3 != '' ? ' ' . $status3 : '');
        $nama_product = $row[7];
        $varian = $row[8];
        $sku_toko = strval($row[6]);
        $sku_toko2 = explode('-', $sku_toko)[0]; // Ambil hanya bagian pertama sebelum "-"
        if (preg_match('/Pack-(\d+)/', $sku_toko, $matches)) {
            $jumlah_dibeli = $row[9] * intval($matches[1]);
        } else {
            $jumlah_dibeli = intval($row[9]);
        }
        $penerima = $row[42];
        $kurir = $row[38];
        $kurir2 = $row[39];
        $kurirFinal = $kurir . ' ' . $kurir2;
        $tanggal_kirim = $row[29];
        $tgl_kirim_formatted = !empty($tanggal_kirim) ? DateTime::createFromFormat('d/m/Y H:i:s', $tanggal_kirim)->format('Y-m-d H:i:s') : '';
        $waktu_kirim = !empty($tanggal_kirim) ? DateTime::createFromFormat('d/m/Y H:i:s', $tanggal_kirim)->format('H:i:s') : '';
        if ($statusMp === 'To ship Awaiting shipment') {
            $stmt = $conn->prepare("SELECT id_product, id_toko, sku_toko FROM toko_id WHERE sku_toko = ?");
            $stmt->bind_param("s", $sku_toko2);
            $stmt->execute();
            $dataselect = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_toko = $dataselect ? $dataselect['id_toko'] : '0';
            $id_orderitem = "$invoice $sku_toko $varian";
            $stmt = $conn->prepare("SELECT * FROM shop_id WHERE sku_toko = ? AND invoice = ?");
            $stmt->bind_param("ss", $sku_toko, $invoice);
            $stmt->execute();
            $stmt->store_result();
            $cancel = $stmt->num_rows;
            $stmt->close();
            if ($cancel == 0) {
                $select = mysqli_query($conn, "SELECT * FROM pre_order_stock_check WHERE sku_toko = '$sku_toko' AND invoice = '$invoice'");
                if (mysqli_num_rows($select) == 0) {
                    // Hitung stok awal hanya jika SKU belum ada di array stok_per_sku
                    if (!isset($stok_per_sku[$sku_toko2])) {
                        $select2 = mysqli_query($conn, "WITH stok_gudang AS (
                                            SELECT SUM(quantity) AS qty_gudang, id_product FROM gudang_id
                                            GROUP BY id_product
                                            UNION ALL
                                            SELECT SUM(quantity) AS qty_gudang, id_product FROM mateng_id
                                            GROUP BY id_product
                                        )
                                        SELECT 
                                            toko_id.quantity_toko AS quantity_toko,
                                            COALESCE(stok_gudang.qty_gudang, 0) AS qty_gudang,
                                            COALESCE(stok_gudang.qty_gudang, 0) + quantity_toko AS total
                                        FROM toko_id
                                        INNER JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                                        INNER JOIN stok_gudang ON stok_gudang.id_product = list_komponen.id_komponen
                                        WHERE toko_id.sku_toko = '$sku_toko2'
                                        GROUP BY toko_id.id_product
                                    ");
                        $fetch2 = mysqli_fetch_array($select2);
                        $stok_per_sku[$sku_toko2] = isset($fetch2['total']) ? $fetch2['total'] : 0;
                    }
                    // Stok awal adalah stok sebelum dikurangi jumlah pesanan
                    $stok_awal = $stok_per_sku[$sku_toko2];
                    // Kurangi stok berdasarkan jumlah_dibeli
                    $stok_akhir = $stok_awal - $jumlah_dibeli;
                    // Simpan stok terbaru dalam array untuk iterasi berikutnya
                    $stok_per_sku[$sku_toko2] = $stok_akhir;
                    $olshop = 'Tokopedia';
                    // Insert ke pre_order_stock_check
                    $stmt = $conn->prepare("INSERT INTO pre_order_stock_check 
                                    (id_orderitem, invoice, tanggal_bayar, nama_product, sku_toko, jumlah, id_toko, olshop, date_load, stok_akhir, stok_awal) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        "sssssssssss",
                        $id_orderitem,
                        $invoice,
                        $tanggal_bayar,
                        $nama_product,
                        $sku_toko,
                        $jumlah_dibeli,
                        $id_toko,
                        $olshop,
                        $date,
                        $stok_akhir,
                        $stok_awal
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    header('location:?url=preorder&olshop=Tokopedia');
}

if (isset($_POST["tiktokimport"])) {
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');

    echo "<table border='1' cellspacing='0' cellpadding='5'>";
    echo "<tr>
            <th>No</th>
            <th>Invoice</th>
            <th>Resi</th>
            <th>Tanggal Bayar</th>
            <th>Status MP</th>
            <th>Nama Produk</th>
            <th>Varian</th>
            <th>Nama Final</th>
            <th>SKU Toko</th>
            <th>SKU Toko 2</th>
            <th>Jumlah Dibeli</th>
            <th>Penerima</th>
            <th>Kurir</th>
            <th>Tanggal Kirim</th>
          </tr>";

    $i = 0;
    $invoice_colors = []; // Menyimpan warna per invoice

    function generateRandomColor()
    {
        return sprintf("#%02x%02x%02x", rand(150, 255), rand(150, 255), rand(150, 255));
    }

    foreach (array_slice($data, 2) as $row) { // Lewati 2 baris pertama, mulai dari baris ke-3
        $invoice = $row[0];

        // Jika invoice belum memiliki warna, buat warna baru
        if (!isset($invoice_colors[$invoice])) {
            $invoice_colors[$invoice] = generateRandomColor();
        }
        $row_color = $invoice_colors[$invoice]; // Ambil warna untuk invoice ini

        $resi = $row[37] ?: '';
        $tgl_bayar = $row[28] ?: '';
        $tgl_bayar_formatted = !empty($tgl_bayar) ? DateTime::createFromFormat('d/m/Y H:i:s', $tgl_bayar)->format('Y-m-d H:i:s') : '';
        $status1 = $row[1];
        $status2 = $row[2];
        $status3 = $row[3];
        $statusMp = $status1 . ' ' . $status2 . ($status3 != '' ? ' ' . $status3 : '');
        $nama_product = $row[7];
        $varian = $row[8];
        $nama_final = $nama_product . ' - ' . $varian;
        $sku_toko = strval($row[6]);
        $sku_toko2 = explode('-', $sku_toko)[0];
        $jumlah_dibeli = intval($row[9]);
        $penerima = $row[42];
        $kurir = $row[38];
        $tanggal_kirim = $row[29];
        $tgl_kirim_formatted = !empty($tanggal_kirim) ? DateTime::createFromFormat('d/m/Y H:i:s', $tanggal_kirim)->format('Y-m-d H:i:s') : '';

        // if ($statusMp == 'To ship Awaiting shipment') {
        echo "<tr style='background-color: $row_color;'>
                <td>" . (++$i) . "</td>
                <td>$invoice</td>
                <td>$resi</td>
                <td>$tgl_bayar_formatted</td>
                <td>$statusMp</td>
                <td>$nama_product</td>
                <td>$varian</td>
                <td>$nama_final</td>
                <td>$sku_toko</td>
                <td>$sku_toko2</td>
                <td>$jumlah_dibeli</td>
                <td>$penerima</td>
                <td>$kurir</td>
                <td>$tgl_kirim_formatted</td>
              </tr>";
    }
    // }

    echo "</table>";
}

if (isset($_POST["tiktokped"])) {
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    foreach (array_slice($data, 2) as $row) { // Lewati 2 baris pertama, mulai dari baris ke-3
        $invoice = $row[0];
        $resi = $row[37] ?: '';
        $tgl_bayar = $row[28] ?: '';
        $tgl_bayar_formatted = !empty($tgl_bayar) ? DateTime::createFromFormat('d/m/Y H:i:s', $tgl_bayar)->format('Y-m-d H:i:s') : '';
        $status1 = $row[1];
        $status2 = $row[2];
        $status3 = $row[3];
        $statusMp = $status1 . ' ' . $status2 . ($status3 != '' ? ' ' . $status3 : '');
        $nama_product = $row[7];
        $varian = $row[8];
        $nama_final = $nama_product . ' - ' . $varian;
        $sku_toko = strval($row[6]);
        $sku_toko2 = explode('-', $sku_toko)[0];
        $jumlah_dibeli = intval($row[9]);
        $penerima = $row[42];
        $kurir = $row[38];
        $kurir2 = $row[39] ?: '';
        $kurirFinal = $kurir . ($kurir2 != '' ? ' ' . $kurir2 : '');
        $tanggal_kirim = $row[29];
        $tgl_kirim_formatted = !empty($tanggal_kirim) ? DateTime::createFromFormat('d/m/Y H:i:s', $tanggal_kirim)->format('Y-m-d H:i:s') : '';
        $waktu_kirim = !empty($tanggal_kirim) ? DateTime::createFromFormat('d/m/Y H:i:s', $tanggal_kirim)->format('H:i:s') : '';
        if ($statusMp == 'To ship Awaiting collection' || ((stripos($kurir, 'sameday') !== false || stripos($kurir, 'same day') !== false || stripos($kurir, 'instant') !== false) && $statusMp == 'To ship Awaiting shipment')) {
            $stmt = $conn->prepare("SELECT id_product, id_toko, sku_toko FROM toko_id WHERE sku_toko = ?");
            $stmt->bind_param("s", $sku_toko2);
            $stmt->execute();
            $dataselect = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_toko = $dataselect ? $dataselect['id_toko'] : '0';
            $id_orderitem = "$invoice $sku_toko $varian";
            // Check if record already exists
            $stmt = $conn->prepare("SELECT id_orderitem FROM temporary_shop_id WHERE id_orderitem = ?");
            $stmt->bind_param("s", $id_orderitem);
            $stmt->execute();
            $stmt->store_result();
            $cancel = $stmt->num_rows;
            $stmt->close();
            if ($cancel == 0) {
                $stmt = $conn->prepare("INSERT INTO temporary_shop_id (id_orderitem, invoice, tanggal_bayar, id_product, sku_toko, jumlah, penerima, kurir, resi, tanggal_pengiriman, waktu_pengiriman, nama_product, olshop, status_mp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $olshop = "Tokopedia";
                $stmt->bind_param("ssssssssssssss", $id_orderitem, $invoice, $tgl_bayar_formatted, $id_toko, $sku_toko, $jumlah_dibeli, $penerima, $kurirFinal, $resi, $tgl_kirim_formatted, $waktu_kirim, $nama_final, $olshop, $statusMp);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE tracking SET status_mp = ?, no_resi = ? WHERE invoice = ?");
            $stmt->bind_param("sss", $statusMp, $resi, $invoice);
            $stmt->execute();
            $stmt->close();
            // Update `shop_id` table
            $stmt = $conn->prepare("UPDATE shop_id SET status_mp = ?, resi = ? WHERE invoice = ? AND nama_product = ?");
            $stmt->bind_param("ssss", $statusMp, $resi, $invoice, $nama_final);
            $stmt->execute();
            $stmt->close();
            // Check and insert into `history_tokped`
            $stmt = $conn->prepare("SELECT DISTINCT invoice, status_mp FROM shop_id WHERE invoice = ? AND olshop = 'Tokopedia'");
            $stmt->bind_param("s", $invoice);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $shopinvoice = $row['invoice'];
                $status_mp = $row['status_mp'];
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM history_tokped WHERE invoice = ? AND status_terakhir = ?");
                $stmt->bind_param("ss", $shopinvoice, $statusMp);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows == 0) {
                    $stmt->close();
                    $unique_id = "$shopinvoice $statusMp $date";
                    $stmt = $conn->prepare("INSERT INTO history_tokped (unique_id, invoice, status_terakhir, date) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $unique_id, $invoice, $statusMp, $date);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt->close();
            }
        }
    }
    header('location:?url=temporary');
}

if (isset($_POST["preordershopee"])) {
    $excel_file = $_FILES["excel_file"]["tmp_name"];
    $spreadsheet = IOFactory::load($excel_file);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');
    $stok_per_sku = [];
    $header = [];
    foreach ($worksheet->getRowIterator(1, 1) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $header[] = $cell->getValue();
        }
    }

    foreach ($worksheet->getRowIterator(2) as $row) {
        $data = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $data[] = $cell->getValue();
        }

        $invoice = $data[array_search('No. Pesanan', $header)];
        $status = $data[array_search('Status Pesanan', $header)];
        $statusRetur = $data[array_search('Status Pembatalan/ Pengembalian', $header)];
        $resi = $data[array_search('No. Resi', $header)];
        $kurir = $data[array_search('Opsi Pengiriman', $header)];
        $tglbayar = $data[array_search('Waktu Pembayaran Dilakukan', $header)];
        $nama_product = $data[array_search('Nama Produk', $header)];
        $variasi = $data[array_search('Nama Variasi', $header)];
        $sku_toko = $data[array_search('Nomor Referensi SKU', $header)];
        $jumlah_dibeli = $data[array_search('Jumlah', $header)];
        $penerima = $data[array_search('Nama Penerima', $header)];
        $tanggal_bayar = $tglbayar;
        $string = strlen($nama_product);
        $varian = substr($nama_product, max(0, $string - 20), 20);
        if ($status == 'Perlu Dikirim') {
            $stmt = $conn->prepare("SELECT id_product, id_toko FROM toko_id WHERE sku_toko = ?");
            $stmt->bind_param("s", $sku_toko);
            $stmt->execute();
            $dataselect = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $id_toko = $dataselect ? $dataselect['id_toko'] : '0';
            $id_orderitem = "$invoice $sku_toko $varian";
            $stmt = $conn->prepare("SELECT * FROM shop_id WHERE sku_toko = ? AND invoice = ?");
            $stmt->bind_param("ss", $sku_toko, $invoice);
            $stmt->execute();
            $stmt->store_result();
            $cancel = $stmt->num_rows;
            $stmt->close();
            if ($cancel == 0) {
                $select = mysqli_query($conn, "SELECT * FROM pre_order_stock_check WHERE sku_toko = '$sku_toko' AND invoice = '$invoice'");
                if (mysqli_num_rows($select) == 0) {
                    if (!isset($stok_per_sku[$sku_toko])) {
                        $select2 = mysqli_query($conn, "WITH stok_gudang AS (
                                        SELECT SUM(quantity) AS qty_gudang, id_product FROM gudang_id GROUP BY id_product
                                        UNION ALL
                                        SELECT SUM(quantity) AS qty_gudang, id_product FROM mateng_id GROUP BY id_product
                                    )
                                    SELECT 
                                        toko_id.quantity_toko AS quantity_toko,
                                        COALESCE(stok_gudang.qty_gudang, 0) AS qty_gudang,
                                        COALESCE(stok_gudang.qty_gudang, 0) + quantity_toko AS total
                                    FROM toko_id
                                    LEFT JOIN list_komponen ON list_komponen.id_product_finish = toko_id.id_product
                                    LEFT JOIN stok_gudang ON stok_gudang.id_product = list_komponen.id_komponen
                                    WHERE toko_id.sku_toko = '$sku_toko'
                                    GROUP BY toko_id.id_product");

                        $fetch2 = mysqli_fetch_array($select2);
                        $stok_per_sku[$sku_toko] = isset($fetch2['total']) ? $fetch2['total'] : 0;
                    }
                    $stok_awal = $stok_per_sku[$sku_toko];
                    $stok_akhir = $stok_awal - $jumlah_dibeli;
                    $stok_per_sku[$sku_toko] = $stok_akhir;
                    $olshop = 'Shopee';
                    $stmt = $conn->prepare("INSERT INTO pre_order_stock_check 
                                    (id_orderitem, invoice, tanggal_bayar, nama_product, sku_toko, jumlah, id_toko, olshop, date_load, stok_akhir, stok_awal) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param(
                        "sssssssssss",
                        $id_orderitem,
                        $invoice,
                        $tanggal_bayar,
                        $nama_product,
                        $sku_toko,
                        $jumlah_dibeli,
                        $id_toko,
                        $olshop,
                        $date,
                        $stok_akhir,
                        $stok_awal
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    header('location:?url=preorder&olshop=Shopee');
}

if (isset($_POST['followuprefund'])) {
    $user = $_POST['user'];
    $note = $_POST['note'];
    $inv  = $_POST['inv'];

    date_default_timezone_set('Asia/Jakarta');
    $date = date('Y-m-d H:i:s');

    // Prepared statement
    $stmt = $conn->prepare("INSERT INTO invoice_note (invoice, note, created_at, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $inv, $note, $date, $user);

    if ($stmt->execute()) {
        // Sukses
        echo '
        <script>
        alert("Catatan berhasil disimpan");
        window.location.href="?url=kelompok";
        </script>';
    } else {
        // Gagal
        echo "Gagal menyimpan catatan: " . $stmt->error;
    }

    $stmt->close();
}