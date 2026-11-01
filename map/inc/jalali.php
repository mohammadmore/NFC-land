<?php
/**
 * NFC Event Tag Scoring - Jalali (Solar Hijri) Date Conversion
 */

if (!defined('APP_INIT')) {
    die('دسترسی مستقیم مجاز نیست.');
}

/**
 * تبدیل میلادی به شمسی (الگوریتم دقیق ریاضی)
 *
 * @param int $gy سال میلادی
 * @param int $gm ماه میلادی (1-12)
 * @param int $gd روز میلادی (1-31)
 * @return array [سال, ماه, روز]
 */
function gregorian_to_jalali($gy, $gm, $gd) {
    $gy = (int)$gy;
    $gm = (int)$gm;
    $gd = (int)$gd;

    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/**
 * تبدیل زمان UTC پایگاه‌داده به تاریخ و ساعت جلالی در منطقه Asia/Tehran
 *
 * @param string|null $utcDateTimeStr زمان ذخیره شده در دیتابیس به فرمت Y-m-d H:i:s
 * @param bool $faDigits تبدیل ارقام به فارسی
 * @param bool $includeTime شامل بودن ساعت
 * @return string
 */
function format_jalali($utcDateTimeStr, $faDigits = true, $includeTime = true) {
    if (!$utcDateTimeStr) {
        return '-';
    }

    try {
        $date = new DateTime($utcDateTimeStr, new DateTimeZone('UTC'));
        $targetTz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Tehran');
        $date->setTimezone($targetTz);

        $gy = (int)$date->format('Y');
        $gm = (int)$date->format('m');
        $gd = (int)$date->format('d');
        $timeStr = $date->format('H:i:s');

        list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);

        $jyStr = sprintf('%04d', $jy);
        $jmStr = sprintf('%02d', $jm);
        $jdStr = sprintf('%02d', $jd);

        $result = "{$jyStr}/{$jmStr}/{$jdStr}";
        if ($includeTime) {
            $result .= " {$timeStr}";
        }

        if ($faDigits && function_exists('fa_num')) {
            return fa_num($result);
        }

        return $result;
    } catch (Exception $e) {
        return (string)$utcDateTimeStr;
    }
}

/**
 * دریافت تاریخ و ساعت جلالی به عنوان دو مقدار مجزا برای خروجی اکسل
 */
function get_jalali_date_time_parts($utcDateTimeStr) {
    if (!$utcDateTimeStr) {
        return ['-', '-'];
    }
    try {
        $date = new DateTime($utcDateTimeStr, new DateTimeZone('UTC'));
        $targetTz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Tehran');
        $date->setTimezone($targetTz);

        $gy = (int)$date->format('Y');
        $gm = (int)$date->format('m');
        $gd = (int)$date->format('d');
        $timeStr = $date->format('H:i:s');

        list($jy, $jm, $jd) = gregorian_to_jalali($gy, $gm, $gd);

        $dateStr = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        return [$dateStr, $timeStr];
    } catch (Exception $e) {
        return [(string)$utcDateTimeStr, ''];
    }
}
