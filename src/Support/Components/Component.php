<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 15.08.16
 */

namespace Kodix\Support\Components;

use CPHPCache;
use CBitrixComponent;

abstract class Component extends CBitrixComponent
{
    /**
     * Имя тега для кеширования.
     *
     * @var string
     */
    protected $cacheTag = 'default';

    /**
     * Флаг необходимости фильтрации по активности.
     *
     * @var bool
     */
    protected $active = true;

    /**
     * Флаг необходимости использования кеширования результата.
     *
     * @var bool
     */
    protected $useCache = true;

    /**
     * Флаг необходимости добавления разрешения в id кеша.
     *
     * @var bool
     */
    protected $cacheGallery = false;

    /**
     * Флаг необходимости фильтрации по датам начала и конца активности.
     *
     * @var bool
     */
    protected $dateFilter = true;

    /**
     * @var array
     */
    protected $additionalCachePath = [];
    /**
     * Поля сортировки
     *
     * @var array $sort
     */
    protected $sort = [];

    /**
     * Поля фильтрации
     *
     * @var array $filter
     */
    protected $filter = [];

    /**
     * Поля для выборки
     *
     * @var array $select
     */
    protected $select = [];

    /**
     * Поля для постраничной навигации
     *
     * @var array $nav
     */
    protected $nav = [];

    /**
     * ID инфоблока элементов
     *
     * @var int $blockId
     */
    protected $blockId = 0;

    /**
     * Массив выбранных элементов
     *
     * @var array $elements
     */
    protected $elements;

    protected $cachePath = 'default';

    /**
     * Стандартные размеры для ресайза
     *
     * @var array $defaultSizes
     */
    protected $resizeSizes = [
        'mobile' => [
            'preview' => [
                'width' => 200,
                'height' => 200,
            ],
            'detail' => [
                'width' => 300,
                'height' => 300,
            ],
        ],
        'tablet' => [
            'preview' => [
                'width' => 300,
                'height' => 300,
            ],
            'detail' => [
                'width' => 400,
                'height' => 400,
            ],
        ],
        'desktop' => [
            'preview' => [
                'width' => 450,
                'height' => 400,
            ],
            'detail' => [
                'width' => 1800,
                'height' => 1200,
            ],
        ],
    ];

    /**
     * Значение отсутствующих свойств в классе по умолчанию
     *
     * @var bool $defaultPropertiesFlag
     */
    protected $defaultPropertiesFlag = true;

    /**
     * Тип ифноблока
     *
     * @var string $blockType
     */
    protected $blockType;

    /**
     * Название компонента
     *
     * @var string $componentName
     */
    protected $componentName;

    /**
     * Название активного шаблона компонента
     *
     * @var string $templateName
     */
    protected $templateName;

    /**
     * Параметры навигации по умолчанию
     *
     * @var array
     */
    protected $defaultNavParams = [
        'nPageSize',
        'nTopCount',
        'bShowAll',
        'iNumPage',
        'nElementID',
    ];

    /**
     * Подготавливает поля для сортировки
     *
     * @return void
     */
    public function prepareSort()
    {
        $this->sort = [$this->arParams['SORT_BY'] => $this->arParams['SORT_ORDER']];

        if(count($this->sort) === 0) {
            $this->sort = ['ID' => 'ASC'];
        }
    }

    /**
     * Подготавливает поля для фильтрации
     *
     * @return void
     */
    public function prepareFilter()
    {
        $active = $this->active;
        $dateFilter = $this->dateFilter;

        $filter = [
            'IBLOCK_TYPE' => $this->blockType,
            'IBLOCK_ID' => $this->blockId,
        ];

        if($active) {
            $filter['ACTIVE'] = 'Y';
        }

        if($dateFilter) {
            $filter = array_merge($filter, [
                "<=DATE_ACTIVE_FROM" => [false, ConvertTimeStamp(false, "FULL")],
                ">=DATE_ACTIVE_TO" => [false, ConvertTimeStamp(false, "FULL")],
            ]);
        }

        if(!empty($this->arParams['FILTER']))
            $filter = array_merge($filter, $this->arParams['FILTER']);

        $this->filter = $filter;
    }

    /**
     * Подгатавливает параметры навигации
     *
     * @return void
     */
    public function prepareNav()
    {
        $nav = [];
        foreach($this->defaultNavParams as $param) {
            if($this->arParams[$param]) {
                $nav[$param] = $this->arParams[$param];
            }
        }

        $this->nav = !empty($nav) ? $nav : false;
    }

    /**
     * Подготавливает поля для выборки
     *
     * @return void
     */
    public function prepareSelect()
    {
        $select = [
            'ID',
            'IBLOCK_ID',
            'PREVIEW_PICTURE',
            'PREVIEW_TEXT',
            'NAME',
            'DATE_CREATE',
            'DATE_ACTIVE_FROM',
            'IBLOCK_SECTION_ID',
        ];
        if(count($this->arParams['PROPS_LIST'])) {
            foreach($this->arParams['PROPS_LIST'] as $code) {
                $select[] = 'PROPERTY_' . $code;
            }
        }
        $this->select = $select;
    }

    /**
     * Добавляет параметры выборки
     *
     * @param string|array $key
     * @return $this
     */
    public function addSelect($field)
    {
        if(is_array($field)) {
            $this->select = array_merge($this->select, $field);
        } elseif(!in_array($field, $this->select)) {
            $this->select[] = $field;
        }

        return $this;
    }

    /**
     * Добавляет условия фильтрации
     *
     * @param string|array $key
     * @param null|string $value
     * @return $this
     */
    public function addFilter($key, $value = null)
    {
        if(is_array($key) && is_null($value)) {
            $this->filter = array_merge($this->filter, $key);
        } else {
            $this->filter[$key] = $value;
        }

        return $this;
    }

    /**
     * Подготавливает arResult с включенным кешированием
     *
     * @return void
     */
    public function prepareResultCached()
    {
        global $CACHE_MANAGER;

        $cache = new CPHPCache();
        $cacheId = $this->getCacheableId();
        $this->componentName = $this->getName();
        $cacheTime = $this->arParams['CACHE_TIME'];
        $this->templateName = $this->getTemplateName() ?: 'default';
        $cachePath = $this->cachePath . '/' . replace_signs($this->componentName) . '/' . replace_signs($this->templateName);
        $res = [];

        if($cacheTime > 0 && $cache->InitCache($cacheTime, $cacheId, $cachePath)) {
            $res = $cache->GetVars();

            if(is_array($res['result']) && (count($res['result']) > 0)) {
                $this->arResult = $res['result'];
            }
        }

        if(!is_array($res['result'])) {
            $this->makeResult();

            $CACHE_MANAGER->RegisterTag($this->cacheTag);
            $CACHE_MANAGER->EndTagCache();

            $cache->StartDataCache($cacheTime, $cacheId, $cachePath);
            $cache->EndDataCache(['result' => $this->arResult]);
        }
    }

    /**
     * Получает id кеша
     *
     * @return string
     */
    protected function getCacheableId()
    {
        return md5($this->getTemplateName() . serialize($this->arParams) . serialize($this->additionalCachePath));
    }

    /**
     * Подготавливает массив arResult без использования кеширования
     *
     * @return void
     */
    public function prepareResultWithoutCache()
    {
        $this->makeResult();
    }

    /**
     * Подготавливает массив arResult
     *
     * @return void
     */
    public function prepareResult()
    {
        if($this->useCache || $this->componentWantsCache()) {
            //Если галерея будет кешироваться
            if($this->cacheGallery) {
                //Добавляем код разрешения экрана в путь кеширования
                //потому что иначе, мы будем кешировать всю галерею
                //для всех устройств одинаково
                $this->addCachePath(get_screen_label());
            }

            $this->prepareResultCached();
        } else {
            $this->prepareResultWithoutCache();
        }
    }

    /**
     * Получает фото-галерею и делает ее ресайз. Все изображениия кешируются.<br>
     * Возвращает массив галереи формата ['small' => ['src' => IMG_PATH], 'full' => ['src' => IMG_PATH]]<br>
     * В результирующем массиве так же содержатся размер изображения, его ширина и высота
     *
     * @param array $element Элемент, галерею которого нужно получить
     * @param array $toMerge Id или массив id изображений, которые необходимо добавить в результирующий массив галереи
     * @param bool|true $toStart Куда добавляются дополнительные изображения. По умолчанию в начало массива.
     * @param string $propertyCode Свойство галереи. По умолчанию GALLERY, вы можете переписать его на свое.
     *
     * @return array
     */
    public function getGallery($element, $toMerge = [], $toStart = true, $propertyCode = 'GALLERY')
    {
        $gallery = [];
        $merge = is_array($toMerge) ? $toMerge : [$toMerge];
        $sizeLabel = get_screen_label();
        $allSizes = $this->resizeSizes;
        $sizes = [
            'preview' => $allSizes[$sizeLabel]['preview'],
            'detail' => $allSizes[$sizeLabel]['detail'],
        ];

        foreach($element['PROPERTY_' . $propertyCode . '_VALUE'] as $key => $image) {
            $gallery[] = [
                'small' => get_resized_cache($image, $sizes['preview']),
                'full' => get_resized_cache($image, $sizes['detail']),
            ];
        }

        foreach($toMerge as $image) {
            $merge[] = [
                'small' => get_resized_cache($image, $sizes['preview']),
                'full' => get_resized_cache($image, $sizes['detail']),
            ];
        }

        if(count($merge) > 0) {
            $toStart ? array_unshift($gallery, $merge) : array_push($gallery, $merge);
        }

        return $gallery;
    }

    /**
     *  Запускает все обработчики и генерирует реультат
     */
    public function boot()
    {
        $this->beforePrepare();
        $this->prepareSort();
        $this->prepareNav();
        $this->prepareSelect();
        $this->prepareFilter();
        $this->additionalPrepares();
        $this->prepareResult();

        //Тут будет вызван метод, результаты которого не подлежат кешированию
        //Поэтому если кеширование включено не создавайте его в классе
        $this->withoutCache();
    }

    public function withoutCache()
    {
        //
    }

    public function additionalPrepares()
    {
        //
    }

    public function beforePrepare()
    {
        //
    }

    /**
     * Добавляет элемент в путь кеша, таким образом изменяя id кеша
     *
     * @param mixed $piece
     * @return $this
     */
    public function addCachePath($piece)
    {
        $this->additionalCachePath[] = $piece;

        return $this;
    }

    /**
     * Удаляет элементы из фильтра
     *
     * @param string|array $code
     * @return $this
     */
    public function removeFromFilter($code)
    {
        return $this->remove($code, $this->filter);
    }

    /**
     * Удаляет элементы из селекта
     *
     * @param string $code
     * @return $this
     */
    public function removeFromSelect($code)
    {
        return $this->remove($code, $this->select);
    }

    /**
     * Удаляет элемент(ы) из массива по его/их коду
     *
     * @param array|string $code
     * @param array $array
     *
     * @return $this
     */
    public function remove($code, &$array)
    {
        if(is_array($code)) {
            foreach($code as $key) {
                if(array_key_exists($key, $array)) {
                    unset($array[$key]);
                }
            }
        } else {
            if(array_key_exists($code, $array)) {
                unset($array[$code]);
            }
        }

        return $this;
    }

    /**
     * Проверяет установлено ли время кеширования в параметрах компонента
     *
     * @return bool
     */
    protected function componentWantsCache()
    {
        return (int)$this->arParams['CACHE_TIME'] > 0;
    }

    /**
     * Обязательный для релазиации метод, вокруг него оборачивается подготовка arResult-ов
     * В этом методе вы должы реализовать наполнение массива $arResult
     */
    abstract public function makeResult();

}