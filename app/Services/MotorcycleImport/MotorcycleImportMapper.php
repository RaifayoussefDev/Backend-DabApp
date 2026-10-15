<?php

namespace App\Services\MotorcycleImport;

/**
 * Maps spreadsheet headers to motorcycle_details columns and normalises cell values.
 *
 * Columns are matched by header NAME (case/punctuation-insensitive), never by
 * position, so the scraper can add/reorder columns without breaking the import.
 * Headers of the previous template (bikez export) are kept as aliases.
 */
class MotorcycleImportMapper
{
    /** Identity keys (not detail columns). */
    public const KEY_MAKE = '__make';
    public const KEY_MODEL = '__model';
    public const KEY_YEAR = '__year';
    public const KEY_CATEGORY = '__category';

    /**
     * normalized header => [column, type]
     * type: float | int | string (varchar 255) | text | medium
     */
    private const HEADERS = [
        // Identity
        'make'        => [self::KEY_MAKE, 'key'],
        'brand'       => [self::KEY_MAKE, 'key'],
        'model'       => [self::KEY_MODEL, 'key'],
        'year'        => [self::KEY_YEAR, 'key'],
        'category'    => [self::KEY_CATEGORY, 'key'],

        // URL / naming
        'sourceurl'      => ['source_url', 'text'],
        'pageurl'        => ['source_url', 'text'],
        'imageurl'       => ['image_url', 'text'],
        'imageurlthumbs' => ['image_url_thumbs', 'text'],
        'title'          => ['title', 'string'],
        'fullname'       => ['title', 'string'],

        // Engine
        'capacity'           => ['displacement', 'float'],
        'displacement'       => ['displacement', 'float'],
        'power'              => ['power', 'float'],
        'torque'             => ['torque', 'float'],
        'fuelconsumption'    => ['fuel_consumption', 'text'],
        'co2emissions'       => ['greenhouse_gases', 'text'],
        'greenhousegases'    => ['greenhouse_gases', 'text'],
        'emissiondetails'    => ['emission_details', 'text'],
        'enginetype'         => ['engine_type', 'string'],
        'powerweightratio'   => ['power_weight_ratio', 'string'],
        'borexstroke'        => ['bore_stroke', 'string'],
        'compression'        => ['compression', 'float'],
        'coolingsystem'      => ['cooling_system', 'text'],
        'fuelsystem'         => ['fuel_system', 'text'],
        'starting'           => ['starter', 'string'],
        'starter'            => ['starter', 'string'],
        'ignition'           => ['ignition', 'text'],
        'valvespercylinder'  => ['valves_per_cylinder', 'int'],
        'lubricationsystem'  => ['lubrication_system', 'text'],
        'sparkplugs'         => ['spark_plugs', 'string'],
        'enginedetails'      => ['engine_details', 'text'],
        'fuelcontrol'        => ['fuel_control', 'string'],
        'batterycapacity'    => ['battery_capacity', 'string'],
        'motortype'          => ['motor_type', 'string'],
        'range'              => ['electric_range', 'string'],
        'chargetime'         => ['charge_time', 'string'],
        'maxrpm'             => ['max_rpm', 'int'],

        // Transmission
        'gearbox'          => ['gearbox', 'text'],
        'transmissiontype' => ['transmission_type', 'text'],
        'finaldrive'       => ['driveline', 'text'],
        'driveline'        => ['driveline', 'text'],
        'clutch'           => ['clutch', 'text'],
        'sprockets'        => ['sprockets', 'string'],
        'chainsize'        => ['chain_size', 'string'],

        // Performance
        'topspeed'                   => ['top_speed', 'float'],
        '060mph'                     => ['acceleration_0_60', 'string'],
        '0100kmh062mph'              => ['acceleration_0_100', 'string'],
        '0100kmh'                    => ['acceleration_0_100', 'string'],
        '14mile04km'                 => ['quarter_mile', 'string'],
        '60140kmh3787mphhighestgear' => ['acceleration_60_140', 'string'],

        // Body
        'wheelbase'               => ['wheelbase', 'float'],
        'overalllength'           => ['overall_length', 'float'],
        'overallwidth'            => ['overall_width', 'float'],
        'overallheight'           => ['overall_height', 'float'],
        'seatheight'              => ['seat_height', 'float'],
        'alternateseatheight'     => ['alternate_seat_height', 'string'],
        'groundclearance'         => ['ground_clearance', 'float'],
        'fuelcapacity'            => ['fuel_capacity', 'float'],
        'reservefuelcapacity'     => ['reserve_fuel_capacity', 'string'],
        'oilcapacity'             => ['oil_capacity', 'string'],
        'carryingcapacity'        => ['carrying_capacity', 'string'],
        'dryweight'               => ['dry_weight', 'float'],
        'wetweight'               => ['wet_weight', 'float'],
        'weightincloilgasetc'     => ['wet_weight', 'float'],
        'frontpercentageofweight' => ['front_weight_percentage', 'string'],
        'rearpercentageofweight'  => ['rear_weight_percentage', 'string'],

        // Chassis
        'frame'                => ['frame_type', 'text'],
        'frametype'            => ['frame_type', 'text'],
        'frontsuspension'      => ['front_suspension', 'text'],
        'rearsuspension'       => ['rear_suspension', 'text'],
        'frontwheeltravel'     => ['front_wheel_travel', 'text'],
        'frontwheeltravelmm'   => ['front_wheel_travel', 'text'],
        'rearwheeltravel'      => ['rear_wheel_travel', 'text'],
        'rearwheeltravelmm'    => ['rear_wheel_travel', 'text'],
        'rakeforkangle'        => ['rake', 'text'],
        'rake'                 => ['rake', 'text'],
        'trail'                => ['trail', 'text'],
        'forktubesize'         => ['fork_tube_size', 'string'],
        'exhaustsystem'        => ['exhaust_system', 'text'],

        // Wheels
        'frontbrakes'         => ['front_brakes', 'text'],
        'frontbrakesdiameter' => ['front_brakes_diameter', 'text'],
        'rearbrakes'          => ['rear_brakes', 'text'],
        'rearbrakesdiameter'  => ['rear_brakes_diameter', 'text'],
        'brakefluid'          => ['brake_fluid', 'string'],
        'abs'                 => ['abs_system', 'string'],
        'fronttyre'           => ['front_tire', 'text'],
        'fronttire'           => ['front_tire', 'text'],
        'reartyre'            => ['rear_tire', 'text'],
        'reartire'            => ['rear_tire', 'text'],
        'tirepressurefront'   => ['tire_pressure_front', 'string'],
        'tirepressurerear'    => ['tire_pressure_rear', 'string'],
        'wheels'              => ['wheels', 'text'],

        // Other
        'coloroptions'                          => ['color_options', 'text'],
        'warranty'                              => ['factory_warranty', 'text'],
        'factorywarranty'                       => ['factory_warranty', 'text'],
        'modificationscomparedtopreviousmodel'  => ['modifications', 'text'],
        'comments'                              => ['comments', 'text'],
        'instruments'                           => ['instruments', 'string'],
        'seat'                                  => ['seat', 'text'],
        'electrical'                            => ['electrical', 'string'],
        'light'                                 => ['light', 'text'],
        'rating'                                => ['rating', 'float'],
        'priceasnewmsrp'                        => ['price', 'float'],

        // How it rides
        'offroadtrail' => ['ride_offroad', 'string'],
        'cornering'    => ['ride_cornering', 'string'],
        'cityriding'   => ['ride_city', 'string'],
        'dailyriding'  => ['ride_daily', 'string'],
        'touring'      => ['ride_touring', 'string'],
        'twoup'        => ['ride_two_up', 'string'],
        'track'        => ['ride_track', 'string'],

        // Writing
        'bestfor'     => ['best_for', 'text'],
        'builtfor'    => ['built_for', 'text'],
        'notidealfor' => ['not_ideal_for', 'text'],
        'summary'     => ['summary', 'text'],
        'verified'    => ['verified', 'string'],
        'all'         => ['writing_all', 'medium'],
    ];

    /** Values the scraper uses for "no data". */
    private const EMPTY_VALUES = ['', '-', '--', '—', 'n/a', 'na', 'null', 'none', '?'];

    public static function normalizeHeader(?string $header): string
    {
        $h = mb_strtolower(trim((string) $header));
        return preg_replace('/[^a-z0-9]/u', '', $h);
    }

    /** True when the row looks like the header row (has both Make and Model). */
    public static function isHeaderRow(array $cells): bool
    {
        $normalized = array_map([self::class, 'normalizeHeader'], $cells);
        return (in_array('make', $normalized, true) || in_array('brand', $normalized, true))
            && in_array('model', $normalized, true);
    }

    /**
     * Builds the column map for a header row.
     *
     * When two headers point at the same column (e.g. "Rake (fork angle)" and
     * "Rake"), both are kept; the first non-empty one wins per row.
     *
     * @return array{map: array<int, array{0:string,1:string}>, unmapped: string[], mapped: string[]}
     */
    public static function buildColumnMap(array $headerCells): array
    {
        $map = [];
        $unmapped = [];
        $mapped = [];

        foreach ($headerCells as $index => $header) {
            $key = self::normalizeHeader($header);
            if ($key === '') {
                continue;
            }
            if (isset(self::HEADERS[$key])) {
                $map[(int) $index] = self::HEADERS[$key];
                $mapped[] = trim((string) $header);
            } else {
                $unmapped[] = trim((string) $header);
            }
        }

        return ['map' => $map, 'unmapped' => $unmapped, 'mapped' => $mapped];
    }

    /**
     * Turns a raw row into ['make','model','year','category','details'=>[column => value]].
     * Empty cells are omitted from details (an empty cell never wipes existing data).
     */
    public static function mapRow(array $cells, array $columnMap): array
    {
        $out = ['make' => '', 'model' => '', 'year' => '', 'category' => '', 'details' => []];

        foreach ($columnMap as $index => [$column, $type]) {
            $raw = self::clean($cells[$index] ?? '');
            if ($raw === null) {
                continue;
            }

            switch ($column) {
                case self::KEY_MAKE:
                    $out['make'] = $out['make'] ?: self::collapse($raw);
                    continue 2;
                case self::KEY_MODEL:
                    $out['model'] = $out['model'] ?: self::collapse($raw);
                    continue 2;
                case self::KEY_YEAR:
                    $out['year'] = $out['year'] ?: $raw;
                    continue 2;
                case self::KEY_CATEGORY:
                    $out['category'] = $out['category'] ?: self::collapse($raw);
                    continue 2;
            }

            if (array_key_exists($column, $out['details'])) {
                continue; // first non-empty header wins
            }

            $value = self::cast($raw, $type);
            if ($value !== null) {
                $out['details'][$column] = $value;
            }
        }

        $out['model'] = self::stripMakePrefix($out['make'], $out['model']);

        return $out;
    }

    /**
     * The scraper writes "Aprilia RS 457" in Model while the catalog stores "RS 457"
     * under brand Aprilia: drop the leading make so existing models match.
     */
    public static function stripMakePrefix(string $make, string $model): string
    {
        if ($make === '' || $model === '') {
            return $model;
        }

        $prefix = mb_strtolower($make) . ' ';
        if (mb_strtolower(mb_substr($model, 0, mb_strlen($prefix))) === $prefix) {
            $stripped = trim(mb_substr($model, mb_strlen($prefix)));
            return $stripped !== '' ? $stripped : $model;
        }

        return $model;
    }

    /**
     * Statistic rows the sheet carries under the header (fill counts "43311",
     * percentages "100.00%"): Make is a number/percentage, never a real brand.
     */
    public static function isSummaryRow(array $cells, array $columnMap): bool
    {
        foreach ($columnMap as $index => [$column]) {
            if ($column === self::KEY_MAKE) {
                $make = trim((string) ($cells[$index] ?? ''));
                return $make !== '' && (bool) preg_match('/^[\d\s.,]+%?$/', $make);
            }
        }
        return false;
    }

    public static function parseYear(string $raw): ?int
    {
        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $raw, $m)) {
            return (int) $m[1];
        }
        if (is_numeric($raw) && (int) $raw >= 1900 && (int) $raw <= 2100) {
            return (int) $raw;
        }
        return null;
    }

    private static function clean(mixed $value): ?string
    {
        $v = trim(str_replace("\u{00A0}", ' ', (string) $value));
        return in_array(mb_strtolower($v), self::EMPTY_VALUES, true) ? null : $v;
    }

    private static function collapse(string $v): string
    {
        return preg_replace('/\s+/u', ' ', $v);
    }

    private static function cast(string $raw, string $type): string|int|float|null
    {
        return match ($type) {
            'float'  => self::firstNumber($raw),
            'int'    => ($n = self::firstNumber($raw)) === null ? null : (int) round($n),
            'string' => mb_substr($raw, 0, 255),
            'text'   => mb_strcut($raw, 0, 65000),
            'medium' => mb_strcut($raw, 0, 16000000),
            default  => $raw,
        };
    }

    /**
     * First number in a spec string, metric value first as the scraper writes it:
     * "649.0 ccm (39.60 cubic inches)" -> 649.0, "1,410 mm" -> 1410, "11,5:1" -> 11.5
     */
    public static function firstNumber(string $raw): ?float
    {
        if (!preg_match('/\d[\d.,]*/', $raw, $m)) {
            return null;
        }

        $num = rtrim($m[0], '.,');

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $num)) {
            $num = str_replace(',', '', $num);       // thousands separator
        } else {
            $num = str_replace(',', '.', $num);      // decimal comma
        }

        // "1.2.3" style leftovers: keep up to the second dot
        if (substr_count($num, '.') > 1) {
            $parts = explode('.', $num);
            $num = $parts[0] . '.' . $parts[1];
        }

        return is_numeric($num) ? (float) $num : null;
    }

    /** Columns the import may write on motorcycle_details. */
    public static function detailColumns(): array
    {
        $cols = [];
        foreach (self::HEADERS as [$column, $type]) {
            if ($type !== 'key') {
                $cols[$column] = $type;
            }
        }
        return $cols;
    }
}
