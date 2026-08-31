<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class PerformerCountry
{
    private const VALID_CODES = [
        'AD','AE','AF','AG','AI','AL','AM','AO','AQ','AR','AS','AT','AU','AW','AX','AZ',
        'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BL','BM','BN','BO','BQ','BR','BS','BT','BV','BW','BY','BZ',
        'CA','CC','CD','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CW','CX','CY','CZ',
        'DE','DJ','DK','DM','DO','DZ','EC','EE','EG','EH','ER','ES','ET','FI','FJ','FK','FM','FO','FR',
        'GA','GB','GD','GE','GF','GG','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GW','GY',
        'HK','HM','HN','HR','HT','HU','ID','IE','IL','IM','IN','IO','IQ','IR','IS','IT','JE','JM','JO','JP',
        'KE','KG','KH','KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB','LC','LI','LK','LR','LS','LT','LU','LV','LY',
        'MA','MC','MD','ME','MF','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MX','MY','MZ',
        'NA','NC','NE','NF','NG','NI','NL','NO','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PL','PM','PN','PR','PS','PT','PW','PY',
        'QA','RE','RO','RS','RU','RW','SA','SB','SC','SD','SE','SG','SH','SI','SJ','SK','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
        'TC','TD','TF','TG','TH','TJ','TK','TL','TM','TN','TO','TR','TT','TV','TW','TZ','UA','UG','UM','US','UY','UZ',
        'VA','VC','VE','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZW',
    ];

    /** Common structured names and ISO alpha-3 values returned by cam feeds. */
    private const ALIASES = [
        'ARG' => 'AR', 'ARGENTINA' => 'AR', 'AUS' => 'AU', 'AUSTRALIA' => 'AU',
        'AUT' => 'AT', 'AUSTRIA' => 'AT', 'BEL' => 'BE', 'BELGIUM' => 'BE',
        'BGR' => 'BG', 'BULGARIA' => 'BG', 'BRA' => 'BR', 'BRAZIL' => 'BR',
        'CAN' => 'CA', 'CANADA' => 'CA', 'CHE' => 'CH', 'SWITZERLAND' => 'CH',
        'CHL' => 'CL', 'CHILE' => 'CL', 'CHN' => 'CN', 'CHINA' => 'CN',
        'COL' => 'CO', 'COLOMBIA' => 'CO', 'CRI' => 'CR', 'COSTA RICA' => 'CR',
        'CZE' => 'CZ', 'CZECHIA' => 'CZ', 'CZECH REPUBLIC' => 'CZ',
        'DEU' => 'DE', 'GERMANY' => 'DE', 'DNK' => 'DK', 'DENMARK' => 'DK',
        'DOM' => 'DO', 'DOMINICAN REPUBLIC' => 'DO', 'ECU' => 'EC', 'ECUADOR' => 'EC',
        'ESP' => 'ES', 'SPAIN' => 'ES', 'EST' => 'EE', 'ESTONIA' => 'EE',
        'FIN' => 'FI', 'FINLAND' => 'FI', 'FRA' => 'FR', 'FRANCE' => 'FR',
        'GBR' => 'GB', 'UNITED KINGDOM' => 'GB', 'GREAT BRITAIN' => 'GB', 'UK' => 'GB',
        'GRC' => 'GR', 'GREECE' => 'GR', 'HRV' => 'HR', 'CROATIA' => 'HR',
        'HUN' => 'HU', 'HUNGARY' => 'HU', 'IDN' => 'ID', 'INDONESIA' => 'ID',
        'IND' => 'IN', 'INDIA' => 'IN', 'IRL' => 'IE', 'IRELAND' => 'IE',
        'ISR' => 'IL', 'ISRAEL' => 'IL', 'ITA' => 'IT', 'ITALY' => 'IT',
        'JPN' => 'JP', 'JAPAN' => 'JP', 'KAZ' => 'KZ', 'KAZAKHSTAN' => 'KZ',
        'KOR' => 'KR', 'SOUTH KOREA' => 'KR', 'LTU' => 'LT', 'LITHUANIA' => 'LT',
        'LVA' => 'LV', 'LATVIA' => 'LV', 'MEX' => 'MX', 'MEXICO' => 'MX',
        'MDA' => 'MD', 'MOLDOVA' => 'MD', 'NLD' => 'NL', 'NETHERLANDS' => 'NL',
        'NOR' => 'NO', 'NORWAY' => 'NO', 'NZL' => 'NZ', 'NEW ZEALAND' => 'NZ',
        'PAN' => 'PA', 'PANAMA' => 'PA', 'PER' => 'PE', 'PERU' => 'PE',
        'PHL' => 'PH', 'PHILIPPINES' => 'PH', 'POL' => 'PL', 'POLAND' => 'PL',
        'PRT' => 'PT', 'PORTUGAL' => 'PT', 'PRI' => 'PR', 'PUERTO RICO' => 'PR',
        'ROU' => 'RO', 'ROMANIA' => 'RO', 'RUS' => 'RU', 'RUSSIA' => 'RU',
        'SRB' => 'RS', 'SERBIA' => 'RS', 'SVK' => 'SK', 'SLOVAKIA' => 'SK',
        'SVN' => 'SI', 'SLOVENIA' => 'SI', 'SWE' => 'SE', 'SWEDEN' => 'SE',
        'THA' => 'TH', 'THAILAND' => 'TH', 'TUR' => 'TR', 'TURKEY' => 'TR',
        'TWN' => 'TW', 'TAIWAN' => 'TW', 'UKR' => 'UA', 'UKRAINE' => 'UA',
        'USA' => 'US', 'UNITED STATES' => 'US', 'UNITED STATES OF AMERICA' => 'US',
        'URY' => 'UY', 'URUGUAY' => 'UY', 'VEN' => 'VE', 'VENEZUELA' => 'VE',
        'VNM' => 'VN', 'VIETNAM' => 'VN', 'ZAF' => 'ZA', 'SOUTH AFRICA' => 'ZA',
    ];

    public static function normalize(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $country = trim((string) $value);
        $localized = self::localizedAlias($country);
        if ($localized !== null) {
            return $localized;
        }
        $country = strtoupper($country);
        $country = preg_replace('/[._-]+/', ' ', $country) ?? '';
        $country = preg_replace('/\s+/', ' ', $country) ?? '';
        if (in_array($country, self::VALID_CODES, true)) {
            return $country;
        }

        return self::ALIASES[$country] ?? null;
    }

    private static function localizedAlias(string $country): ?string
    {
        return match (true) {
            preg_match('/^россия$/iu', $country) === 1 => 'RU',
            preg_match('/^германия$/iu', $country) === 1 => 'DE',
            preg_match('/^украина$/iu', $country) === 1 => 'UA',
            default => null,
        };
    }

    public static function label(string $code, string $locale): string
    {
        $code = self::normalize($code) ?? '';
        if ($code === '') {
            return '';
        }
        $bundled = CountryNames::localized($code, $locale);
        if ($bundled !== null) {
            return $bundled;
        }
        if (class_exists(\Locale::class)) {
            $label = \Locale::getDisplayRegion('und-' . $code, $locale);
            if (is_string($label) && trim($label) !== '' && strtoupper($label) !== $code) {
                return $label;
            }
        }

        return CountryNames::english($code);
    }
}
