<?php
function removeAccents($str) {
    $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", "a", $str);
    $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", "e", $str);
    $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", "i", $str);
    $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", "o", $str);
    $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", "u", $str);
    $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", "y", $str);
    $str = preg_replace("/(đ)/", "d", $str);
    $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", "a", $str);
    $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", "e", $str);
    $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", "i", $str);
    $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", "o", $str);
    $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", "u", $str);
    $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", "y", $str);
    $str = preg_replace("/(Đ)/", "d", $str);
    return strtolower(trim($str));
}

function testMatchHeader($headerText) {
    $h = removeAccents($headerText);
    
    // 1. Kiểm tra Đơn vị / Đại lý / Công ty / Nơi công tác trước
    if (strpos($h, 'don vi') !== false || strpos($h, 'dai ly') !== false || strpos($h, 'cong ty') !== false || strpos($h, 'noi cong tac') !== false || strpos($h, 'org') !== false || strpos($h, 'agency') !== false) {
        return 'organization';
    }
    // 2. Kiểm tra Bàn ngồi / Mã bàn
    if (strpos($h, 'ban') !== false || strpos($h, 'table') !== false) {
        return 'table_code';
    }
    // 3. Kiểm tra Mã trúng thưởng / Bốc thăm / Quay thưởng
    if (strpos($h, 'thuong') !== false || strpos($h, 'boc tham') !== false || strpos($h, 'quay') !== false || strpos($h, 'lucky') !== false) {
        return 'lucky_code';
    }
    // 4. Kiểm tra Mã KH / Mã đối chiếu
    if (strpos($h, 'ma kh') !== false || strpos($h, 'doi chieu') !== false || strpos($h, 'ma khach') !== false || strpos($h, 'code') !== false) {
        return 'customer_code';
    }
    // 5. Kiểm tra Số điện thoại / SĐT
    if (strpos($h, 'sdt') !== false || strpos($h, 'dien thoai') !== false || strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) {
        return 'phone';
    }
    // 6. Kiểm tra Họ và tên
    if (strpos($h, 'ho') !== false || strpos($h, 'ten') !== false || strpos($h, 'name') !== false) {
        return 'full_name';
    }
    return 'unknown';
}

$testHeaders = [
    'Mã KH',
    'MA KH',
    'Họ và tên',
    'HO VA TEN',
    'Số điện thoại',
    'SDT',
    'Đơn vị / Đại lý',
    'DON VI / DAI LY',
    'Tên Đơn vị',
    'Đơn vị công tác',
    'Công ty',
    'Bàn ngồi',
    'Mã bàn',
    'Mã trúng thưởng',
    'MÃ TRÚNG THƯỞNG'
];

foreach ($testHeaders as $th) {
    echo "Header: '{$th}' => Matched: " . testMatchHeader($th) . "\n";
}
