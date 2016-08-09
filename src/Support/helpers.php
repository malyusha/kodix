<?php
use Kodix\Support\Arr;
use Kodix\Support\Collection;
use Kodix\Support\Debug\Dumper;

if(!function_exists('replace_signs')) {

    /**
     * Заменяет знаки в строке на нужный нам
     *
     * @param string $str Строка, в которой заменяем
     * @param string $delimiter Знак, на который заменяем
     * @param array $additional Дополнительные знаки для замены
     * @param array $ignore Игнорируемые знаки
     *
     * @return mixed
     */
    function replace_signs($str, $delimiter = '_', array $additional = [], array $ignore = []) {
        $replaces = array_merge([':', '.', ',', '-'], $additional);
        $symbols = implode('', array_diff($replaces, $ignore));
        $symbols = preg_quote($symbols);

        return preg_replace("/[$symbols]/u", $delimiter, $str);
    }
}

if(!function_exists('formatBaseCurrency')) {
    /**
     * Форматирует курс
     *
     * @param $currency
     * @return mixed
     */
    function formatBaseCurrency($currency, $replace = []) {
        $arCurrency = [
            'RUB' => $replace['RUB'] ? $replace['RUB'] : 'руб.',
            'USD' => $replace['USD'] ? $replace['USD'] : '$',
            'EUR' => $replace['EUR'] ? $replace['EUR'] : '€'
        ];

        return $arCurrency[$currency];
    }
}

if(!function_exists('collect')) {
    /**
     * Возвращает экземпляр коллекции
     *
     * @param array $collection
     * @return Collection
     */
    function collect(array $collection) {
        return new Collection($collection);
    }
}

if(!function_exists('dd')) {
    /**
     * Делает стилизованный дамп переменных
     */
    function dd() {
        array_map(function($var) {
            (new Dumper())->dump($var);
        }, func_get_args());

        die(1);
    }
}

if(!function_exists('init_map')) {
    /**
     * Инициализирует карту Google
     * Для инициализации должен быть подключени скрипт:
     * @link https://maps.googleapis.com/maps/api/js
     *
     * @param string|float $latitude Широта
     * @param string|float $longitude Долгота
     * @param string $mapId Id блока, в котором должна быть инициализирована карта
     * @return void
     */
    function init_map($latitude, $longitude, $mapId = 'contacts_map', $zoom = 10, $markers = []) {
        $markers = count($markers) == 1 ? [$markers] : $markers;
        $markers = json_encode($markers);
        echo <<<EOT_MAP
            <script>
                (function(maps){
                    if(typeof google === 'undefined') {
                        throw new Error('Google maps script are required. Include this script https://maps.googleapis.com/maps/api/js');
                    }

                    var markers = $markers;
                    var zoom = parseInt($zoom) || 10;
                    var lat = parseFloat($latitude);
                    var lng = parseFloat($longitude);
                    var map = new maps.Map(document.getElementById("$mapId"), {
                        center: {lat: lat, lng: lng},
                        zoom: zoom,
                        disableDefaultUI: true
                    });

                    markers.forEach(function(marker) {
                        console.log(parseFloat(marker.lat) || lat);
                        var markerOpts = {
                            map: map,
                            position: {
                              lat: parseFloat(marker.lat) || lat,
                              lng: parseFloat(marker.lng) || lng
                            },
                            icon: marker.icon
                        };
                        if(marker.label) {
                            markerOpts.labelContent = marker.label.content;
                            markerOpts.labelClass = marker.label.class;

                            new MarkerWithLabel(markerOpts);
                        } else {
                            new google.maps.Marker(markerOpts);
                        }
                    });
                })(google.maps);
            </script>
EOT_MAP;

    }
}

if(!function_exists('get_screen_label')) {
    /**
     * Получает код типа текущего устройства
     *
     * @return string
     */
    function get_screen_label() {
        $detect = new Mobile_Detect();
        $label = 'desktop';
        if($detect->isTablet()) {
            $label = 'tablet';
        } elseif($detect->isMobile()) {
            $label = 'mobile';
        }

        return $label;
    }
}

if(!function_exists('get_resized_cache')) {
    /**
     * Возвращает ресайзнутую картинку по ее id и размерам
     *
     * @param $imageId
     * @param array $sizes
     * @return mixed
     */
    function get_resized_cache($imageId, array $sizes) {
        return CFile::ResizeImageGet($imageId, $sizes, BX_RESIZE_IMAGE_PROPORTIONAL, true);
    }
}

if( !function_exists('user_is') ) {
    function user_is($groupCode, $userId = false)
    {
        global $USER;
        $groups = [];
        $group = new CGroup();
        $groupCode = is_array($groupCode) ? $groupCode : [$groupCode];
        $by = 'NAME';
        $order = 'ASC';
        $rsGroups = $group->GetList($by, $order, ['STRING_ID' => implode('|', $groupCode)]);
        if( $rsGroups->AffectedRowsCount() == 0 ) {
            return false;
        }

        while($arGroup = $rsGroups->Fetch()) {
            $groups[$arGroup['ID']] = $arGroup;
        }

        $userGroups = $userId ? $USER->GetUserGroup($userId) : $USER->GetUserGroupArray();
        foreach($userGroups as $groupId) {
            if( $groups[$groupId] ) {
                return true;
            }
        }

        return false;
    }
}

if( !function_exists('return_json') ) {
    function return_json($array)
    {
        echo json_encode($array);
        die;
    }
}

if (! function_exists('value')) {
    /**
     * Получает значение по умолчанию от переданного значения.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('data_get')) {
    /**
     * Получает элемент из массива или объекта используя "dot" нотацию.
     *
     * @param  mixed   $target
     * @param  string|array  $key
     * @param  mixed   $default
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        while (($segment = array_shift($key)) !== null) {
            if ($segment === '*') {
                if ($target instanceof Collection) {
                    $target = $target->all();
                } elseif (! is_array($target)) {
                    return value($default);
                }

                $result = Arr::pluck($target, $key);

                return in_array('*', $key) ? Arr::collapse($result) : $result;
            }

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (! function_exists('class_basename')) {
    /**
     * Получает название переданного класса/объекта
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if(! function_exists('remove_cms_headers')) {
    /**
     * Удаляет заголовки битрикса и языка из отправляемых сервером.
     * Зарегистрируйте эту функцию на событие OnBeforeProlog для полного профита.
     */
    function remove_cms_headers() {
        header_remove('X-Powered-CMS');
        header_remove('X-Powered-By');
    }
}