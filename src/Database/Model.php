<?php
/**
 * Created by Kodix.
 * Developer: Igor Malyuk
 * Email: support@kodix.ru
 * Date: 16.06.16
 */

namespace Kodix\Database;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\SystemException;
use CFile;
use CSite;
use DateTime;
use ArrayAccess;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use DateTimeInterface;
use Kodix\Database\Highload\Builder;
use Kodix\Database\Highload\ManagerException;
use Kodix\Database\Relations\BelongsTo;
use Kodix\Database\Relations\BelongsToMany;
use Kodix\Database\Relations\HasMany;
use Kodix\Database\Relations\HasManyThrough;
use Kodix\Database\Relations\HasOne;
use Kodix\Database\Relations\Relation;
use Kodix\Support\Arr;
use Kodix\Support\Carbon;
use Kodix\Support\Str;
use Kodix\Database\Collection;
use Kodix\Contracts\Support\Jsonable;
use Kodix\Contracts\Support\Arrayable;
use Kodix\Database\Exceptions\ModelException;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use Bitrix\Highloadblock\HighloadBlockTable as Table;
use Kodix\Support\Collection as BaseCollection;
use LogicException;

/**
 * Class Model
 *
 * @package Kodix\Database
 * @method Builder where($column, $operator = null, $value = null)
 * @method Builder orWhere($column, $operator = null, $value = null)
 * @method static first($columns = [])
 */
abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * Название поля последнего обновления
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Название поля даты создания
     */
    const CREATED_AT = 'created_at';

    /**
     * Таблица модели
     *
     * @var string
     */
    protected $table;

    /**
     * Разделитель копеек для формата денег
     *
     * @var string
     */
    protected $moneyPoint = '.';

    /**
     *
     *
     * Разделитель тысяч для формата типа денег
     *
     * @var string
     */
    protected $thousandsSeparator = ' ';

    /**
     * Количество десятков после запятой для форамата типа 'money'
     *
     * @var int
     */
    protected $moneyDecimal = 2;

    /**
     * Атрибуты, которые будут видимы в модели
     *
     * @var array
     */
    protected $visible = [];

    /**
     * Атрибуты, которые будут скрыты модели
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * ААтрибуты, которые должны быть подставлены в модель
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Первичный ключ модели
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Флаг авто-инкременты таблицы модели
     *
     * @var bool
     */
    protected $incrementing = true;

    /**
     * Те колонки модели, у которых по умолчанию нет префикса и он не должен подставляться
     *
     * @var array $withoutPrefix
     */
    protected $withoutPrefix = [];

    /**
     * Атрибуты модели
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Сохраненные атрибуты модели до всех обновлений
     *
     * @var array
     */
    protected $original = [];

    /**
     * Поля модели, которые должны быть преобразованы к определенным типам
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Формат даты для колонок модели типа "Дата"
     *
     * @var string
     */
    protected $dateFormat = 'd.m.Y H:i:s';

    /**
     * Папка, в которой по умолчанию лежат файлы таблицы модели
     *
     * @var string
     */
    protected $uploadFolder = '/upload/';

    /**
     * Отношения модели
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Отношения, которые должны быть загружены всегда
     *
     * @var array
     */
    protected static $preloadedRelations = [];

    /**
     * Поля, которые должны быть представлены как списки
     *
     * @var array
     */
    protected $listProperty = [];

    /**
     * Поля, содержащие файлы
     *
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $originalFiles = [];

    /**
     * Префикс полей модели
     *
     * @var string
     */
    public $prefix = 'uf_';

    /**
     * Индикатор, показывающий - создана ли модель в базе.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Индикатор, показывающий - была ли модель создана в течении текущего запроса.
     *
     * @var bool
     */
    public $wasRecentlyCreated = false;

    /**
     * Флаг использования таймштампов моделью.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Флаг того, что аттрибуты в модели записаны в нотации "snake_case".
     *
     * @var bool $snakeAttributes
     */
    protected static $snakeAttributes = true;

    /**
     * Кеш измененных атррибутов для всех классов..
     *
     * @var array
     */
    protected static $mutatorCache = [];

    /**
     * Поля, которые должны быть представлены как дата
     *
     * @var array $dates
     */
    protected $dates = [];

    /**
     * Экземпляр класса работы с  базой
     *
     * @var DataManager
     */
    protected $manager;

    /**
     * Тип первичного ключа модели
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Model constructor.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->syncOriginal();
        $this->syncFiles();
        $this->boot();
    }

    /**
     * Загрузочный метод модели
     */
    protected static function boot()
    {
        //
    }

    /**
     * Заполняет модель массивом значений
     *
     * @param $attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function syncFiles()
    {
        $this->originalFiles = $this->files;

        return $this;
    }

    /**
     * @param $attributes
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Append attributes to query when building a query.
     *
     * @param  array|string $attributes
     *
     * @return $this
     */
    public function append($attributes)
    {
        if (is_string($attributes)) {
            $attributes = func_get_args();
        }

        $this->appends = array_unique(array_merge($this->appends, $attributes));

        return $this;
    }

    /**
     * @return \CFile
     */
    protected function newFileLoader()
    {
        return new CFile();
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param  array $appends
     *
     * @return $this
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Получает id файла из модели
     *
     * @param $attribute
     * @return mixed
     */
    public function getFileId($attribute)
    {
        $attribute = $this->normalizeKey($attribute);

        return (int)$this->attributes[$attribute];
    }

    /**
     * @return string
     */
    public function getUploadFolder()
    {
        return $this->uploadFolder;
    }

    /**
     * Проверяет - была ли модель или переданный(е) атрибуты изменены.
     *
     * @param array|string|null $attributes
     *
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        if (!is_array($attributes)) {
            $attributes = func_get_args();
        }

        $attributes = $this->normalizeKey($attributes);

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает все аттрибуты, которые были изменены с момента последней синхронизации.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key] && !$this->originalIsNumericallyEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];

        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string)$current, (string)$original) === 0;
    }

    /**
     * @return mixed
     * @throws ModelException
     */
    public function getTable()
    {
        if (isset($this->table)) {
            return $this->table;
        }

        throw new ModelException(sprintf('Model %s must have $table property.', get_class($this)));
    }

    /**
     * @return \Carbon\Carbon|mixed|null
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * @return string
     */
    public function getKeyName()
    {
        return strtoupper($this->primaryKey);
    }

    /**
     * Возвращает название внешнего ключа для данной модели
     *
     * @return string
     */
    public function getForeignKey()
    {
        $key = Str::snake(class_basename($this)) . '_id';

        return $this->normalizeKey($key);
    }

    /**
     * Проверяет есть ли у модели мутатор при изменении атрибута
     *
     * @param $key
     *
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set' . Str::studly($key) . 'Attribute');
    }

    /**
     * Устанавливает значение атрибута модели
     *
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $normalizedKey = $this->normalizeKey($key);

        // Первым делом проверим - есть ли у модели мутаторы
        // на добавление свойств - если они есть, то вернем
        // преобразованное свойство
        if ($this->hasSetMutator($key)) {
            $method = 'set' . Str::studly($key) . 'Attribute';

            return $this->{$method}($normalizedKey, $value);
        } elseif ($value && (in_array($key, $this->getDates()) || $this->isDateCastable($key))) {
            $value = $this->fromDateTime($value);
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->asJson($value);
        }

        $this->attributes[$normalizedKey] = $value;

        return $this;
    }

    /**
     * @param $key
     *
     * @return \Carbon\Carbon|mixed|null
     */
    public function getAttribute($key)
    {
        if (array_key_exists($this->normalizeKey($key), $this->attributes) || $this->hasGetMutator($key) || $this->hasFile($key)) {
            return $this->getAttributeValue($key);
        }

        return $this->getRelationValue($key);
    }

    public function hasFile($key)
    {
        return array_key_exists($key, $this->files) && !!$this->files[$key];
    }

    /**
     * @param $key
     *
     * @return mixed|null
     */
    protected function getAttributeFromArray($key)
    {
        $key = $this->normalizeKey($key);

        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }

    /**
     * @param $key
     *
     * @return Carbon|mixed|null
     */
    public function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        if ($this->hasFile($key)) {
            return $this->files[$key];
        }

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        if (!is_null($value) && in_array($key, $this->getDates())) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Возвращает все поля, которые должны быть представлены как даты
     *
     * @return array
     */
    public function getDates()
    {
        return array_unique(array_merge([
            static::CREATED_AT,
            static::UPDATED_AT,
        ], $this->dates));
    }

    /**
     * Возвращает значение как объект Carbon
     *
     * @param  mixed $value
     *
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return $value;
        }
        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return new Carbon($value->format($this->getDateFormat()), $value->getTimezone());
        }

        // If the value is instance of Bitrix's DateTime object, we will create new
        // instance of Carbon.
        if (class_exists('Bitrix\Main\Type\DateTime') && $value instanceof BitrixDateTime) {
            return new Carbon($value->format($this->getDateFormat()), $value->getTimeZone());
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        return Carbon::createFromFormat($this->getDateFormat(), $value);
    }

    /**
     * Получает названия колонок дат по умолчанию
     *
     * @return array
     */
    public function getBaseDatesColumns()
    {
        return [static::UPDATED_AT, static::CREATED_AT];
    }

    /**
     * Возвращает формат даты для модели
     *
     * @return string
     */
    protected function getDateFormat()
    {
        if (!$this->dateFormat) {
            $this->dateFormat = $GLOBALS['DB']->DateFormatToPHP($this->getSite()->GetDateFormat('SHORT'));
        }

        return $this->dateFormat;
    }

    /**
     * Возвращает новый билдер для таблицы модели
     *
     * @return Builder
     */
    public function newBuilder()
    {
        $builder = $this->newHighloadBuilder($this->newManager(), $this->newFileLoader());

        return $builder->setModel($this);
    }

    /**
     * Создает новый Builder
     *
     * @param DataManager $manager
     *
     * @return Builder
     */
    public function newHighloadBuilder(DataManager $manager, CFile $fileLoader)
    {
        return new Builder($manager, $fileLoader);
    }

    /**
     * Создает новый экземпляр компоновщика параметров из таблицы запросов
     *
     * @return DataManager
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     * @throws \Kodix\Database\Highload\ManagerException
     */
    protected function newManager()
    {
        if ($this->manager) {
            return $this->manager;
        }

        try {
            $hlblock = Table::getList(['filter' => ['TABLE_NAME' => $this->table]])->fetch();
            $table = Table::compileEntity($hlblock)->getDataClass();
        } catch (SystemException $e) {
            throw new ManagerException("Can not create manager for model with table {$this->table}");
        }

        return $this->manager = new $table;
    }

    /**
     * Создает новый экземпляр модели из переданных атрибутов
     *
     * @param array $attributes
     * @param bool $exists
     *
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array)$attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Создает создает коллекцию моделей из голых массивов данных
     *
     * @param array $items
     *
     * @return \Kodix\Database\Collection
     */
    public static function hydrate(array $items)
    {
        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item);
        }, $items);

        return $instance->newCollection($items);
    }

    /**
     * @param array $attributes
     *
     * @return \Kodix\Database\Model
     */
    public function newFromBuilder($attributes = [])
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array)$attributes, true);

        return $model;
    }

    /**
     * @param array $attributes
     * @param bool $sync
     *
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Получает объект отношения
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getRelationValue($key)
    {
        // Если ключ уже существует в массиве отношений, значит отношение уже было
        // загружено, поэтому мы просто вернем его из массива потому что нет
        // никакой нужды делать запросы на загрузку отношения два раза.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // Если атрибут существует как метод в текущей модели мы можем сделать вывод,
        // что это отношение и загрузить его и вернуть результат из его запроса,
        // затем положить результаты отношения в текущую модель.
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }
    }

    /**
     * @param $method
     *
     * @return mixed
     */
    protected function getRelationshipFromMethod($method)
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            throw new LogicException('Relationship method must return an object of type ' . 'Kodix\Database\Relations\Relation');
        }

        $this->setRelation($method, $results = $relation->getResults());

        return $results;
    }

    /**
     * Проверяет - было ли загружено отношение с данным ключом.
     *
     * @param $key
     *
     * @return bool
     */
    public function relationLoaded($key)
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (!count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(array_combine($this->appends, $this->appends));
    }

    /**
     * Записывает переданное отношение в модель
     *
     * @param $relation
     * @param $value
     *
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Устанавливает все отношения из переданного массива.
     *
     * @param array $relations
     *
     * @return $this
     */
    public function setRelations(array $relations)
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Обновляет даты создания и обновления модели.
     *
     * @return void
     */
    protected function updateTimestamps()
    {
        $time = $this->freshTimestamp();

        if (!$this->isDirty(static::UPDATED_AT)) {
            $this->setUpdatedAt($time);
        }

        if (!$this->exists && !$this->isDirty(static::CREATED_AT)) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Устанавливает значение поля даты обновления модели.
     *
     * @param $value
     *
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->{static::CREATED_AT} = $value;

        return $this;
    }

    /**
     * Устанавливает значение поля даты создания модели.
     *
     * @param $value
     *
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->{static::UPDATED_AT} = $value;

        return $this;
    }

    /**
     * Возвращает название поля даты обновления модели
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return $this->normalizeKey(static::UPDATED_AT);
    }

    /**
     * Возвращает название поля даты создания модели
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return $this->normalizeKey(static::CREATED_AT);
    }

    /**
     * Получает свежий timestamp для модели
     *
     * @return \Kodix\Support\Carbon
     */
    public function freshTimestamp()
    {
        return new Carbon;
    }

    /**
     * Получает свежий timestamp для модели
     *
     * @return string
     */
    public function freshTimestampString()
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Устанавливает ключ модели для запроса.
     *
     * @param \Kodix\Database\Highload\Builder $query
     *
     * @return \Kodix\Database\Highload\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        //$query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
        $query->setModelPrimary($this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Получает значение primary ключа модель для запроса.
     *
     * @return mixed
     */
    protected function getKeyForSaveQuery()
    {
        if (isset($this->original[$this->getKeyName()])) {
            return $this->original[$this->getKeyName()];
        }

        return $this->getAttribute($this->getKeyName());
    }

    /**
     * @param array $attributes
     *
     * @return static
     */
    public static function create(array $attributes = [])
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * @param array $attributes
     * @param array $options
     *
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * @param array $options
     */
    protected function finishSave(array $options)
    {
        $this->syncOriginal();
        $this->resyncFiles();

        //todo: Touch owners
    }

    /**
     * @param array $options
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        $builder = $this->newBuilder();

        // Если 'saving' событие вернет false, то мы закончим выполнение и вернем false,
        // что будет означать, что сохранение не удалось. Это позволит всем слушателям
        // события отменить сохранение если не прошла валидация или что-либо еще.
        /*if ($this->fireModelEvent('saving') === false) {
            return false;
        }*/

        // Если модель уже существует в базе мы можем просто обновить запись в базе
        // используя текущий ID в фильтрации билдера, что просто обновит эту
        // модель. В противном случае мы выполним операцию 'insert'.
        if ($this->exists) {
            $saved = $this->performUpdate($builder, $options);
        }

        // Если модель новая, мы создадим новую запись в базе, используя занчения
        // из текущей модели. Также, мы обновим ID атрибут модели, установив
        // его значением новый созданый ID строки(авто-инкремент из базы).
        else {
            $saved = $this->performInsert($builder, $options);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Осуществляет операцию обновления модели.
     *
     * @param \Kodix\Database\Highload\Builder $builder
     * @param $options
     *
     * @return bool
     */
    protected function performUpdate(Builder $builder, $options)
    {
        if ($this->isDirty()) {
            // Если событие обновления возвращает false мы отменим обновление, поэтому
            // разработчик может использовать системы валидации в своих моделях
            // и отменить операцию обновления. В противном обновляем модель.
            /*if ($this->fireModelEvent('updating') === false) {
                return false;
            }*/

            // Сначала нам нужно обновить поле даты обновления модели, поэтому мы
            // запустим процесс обновления поля создания и обновления только
            // в том случае, если они есть.
            if ($this->timestamps && Arr::get($options, 'timestamps', true)) {
                $this->updateTimestamps();
            } 

            // Когда мы запустили операцию обновления, мы запустим событие 'updated' для текущего
            // объекта модели. Это даст разработчикам возможность внедриться в эту модель после
            // ее обновления, позволяя производить какие-то дополнительные операции.
            $dirty = $this->getDirty();

            if (count($dirty) > 0) {
                $dirty = $this->insertFiles($builder, $dirty);
                $numRows = $this->setKeysForSaveQuery($builder)->update($dirty);

                //$this->fireModelEvent('updated', false);
            }
        }

        return true;
    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param $attributes
     *
     * @return array
     */
    protected function insertFiles(Builder $builder, $attributes)
    {
        foreach ($this->originalFiles as $key) {
            $key = $this->normalizeKey($key);
            if (array_key_exists($key, $attributes)) {
                $this->attributes[$key] = $attributes[$key] = $builder->createFile($attributes[$key]);
            }
        }

        return $attributes;
    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param $options
     */
    protected function performInsert(Builder $builder, $options = [])
    {
        if ($this->timestamps && Arr::get($options, 'timestamps', true)) {
            $this->updateTimestamps();
        }

        $attributes = $this->insertFiles($builder, $this->attributes);

        $this->insertAndSetId($builder, $attributes);

        $this->exists = true;

        $this->wasRecentlyCreated = true;

        return true;

    }

    /**
     * @param \Kodix\Database\Highload\Builder $builder
     * @param $attributes
     */
    protected function insertAndSetId(Builder $builder, $attributes)
    {
        $id = $builder->insert($attributes);

        $this->setAttribute($this->getKeyName(), $id);
    }

    /**
     * Получает значение флага авто-инкремента.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return $this->incrementing;
    }

    /**
     * Проверяет - использует ли модель таймштампы.
     *
     * @return bool
     */
    public function usesTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * Конвертирует DateTime объект в строку
     *
     * @param  \DateTime|int $value
     *
     * @return string
     */
    public function fromDateTime($value)
    {
        $format = $this->getDateFormat();

        $value = $this->asDateTime($value);

        return $value->format($format);
    }

    /**
     * Получает измененные атрибуты для текущего класса.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = get_called_class();

        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Обновляет объект модели из базы данных
     *
     *
     */
    public function fresh($columns = [])
    {
        if (!$this->exists) {
            return;
        }

        $key = $this->getKeyName();

        $this->resyncFiles();

        return $this->where($key, $this->getKey())->first($columns);
    }

    protected function resyncFiles()
    {
        $this->files = $this->originalFiles;

        return $this;
    }

    /**
     * Возвращает и кеширует преобразованные свойства класса
     *
     * @param  string $class
     *
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        $mutatedAttributes = [];

        //Мы находим все мутированные аттрибуты и положим их в кеш чтобы иметь быстрый доступ к ним
        if (preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches)) {
            foreach ($matches[1] as $match) {
                if (static::$snakeAttributes) {
                    $match = Str::snake($match);
                }

                $mutatedAttributes[] = lcfirst($match);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Получает объект сайта
     *
     * @return CSite
     */
    public function getSite()
    {
        return new CSite();
    }

    /**
     * Создает новую коллекцию из переданных моделей
     *
     * @param array $models
     *
     * @return \Kodix\Database\Collection
     */
    public function newCollection($models = [])
    {
        return new Collection($models);
    }

    /**
     * Приводит к массиву все атрибуты
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->getArrayableAttributes();

        // Если аттрибут это дата, то мы переведем его в DateTime / Carbon
        // объекты. Сделано это для того, чтобы в виде массив / JSON объекта
        // мы получали отформатированную дату
        foreach ($this->getDates() as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            $attributes[$key] = $this->serializeDate($this->asDateTime($attributes[$key]));
        }

        $mutatedAttributes = $this->getMutatedAttributes();

        foreach ($mutatedAttributes as $key) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray($key, $attributes[$key]);
        }

        foreach ($this->getCasts() as $key => $value) {
            if (!array_key_exists($key, $attributes) || in_array($key, $mutatedAttributes)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute($key, $attributes[$key]);

            if ($attributes[$key] && (in_array($value, ['date', 'datetime'], true))) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }
        }

        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->denormalizeArrayAttributes($this->attributes));
    }

    /**
     * @return array
     */
    protected function getArrayableRelations()
    {
        return $this->relations;
    }

    /**
     * @return array
     */
    public function relationsToArray()
    {
        $attributes = [];

        foreach ($this->getArrayableRelations() as $key => $value) {

            if ($value instanceof Arrayable) {
                $relation = $value->toArray();
                // Отношение belongsToMany
            } elseif (is_null($value)) {
                $relation = $value;
            }

            if (static::$snakeAttributes) {
                $key = Str::snake($key);
            }

            if (isset($relation) || is_null($value)) {
                $attributes[$key] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array $visible
     *
     * @return $this
     */
    public function setVisible(array $visible)
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array $hidden
     *
     * @return $this
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * @param array $values
     *
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->getVisible()) > 0) {
            return array_intersect_key($values, array_flip($this->getVisible()));
        }

        return array_diff_key($values, array_flip($this->getHidden()));
    }

    /**
     * Денормализует все ключи атрибутов модели как массива.
     *
     * @param array $array
     *
     * @return array
     */
    public function denormalizeArrayAttributes(array $array)
    {
        return $this->normalizeOrDenormalizeArray($array, false);
    }

    /**
     * @param array $array
     * @return array
     */
    public function normalizeArrayAttributes(array $array)
    {
        return $this->normalizeOrDenormalizeArray($array);
    }

    /**
     * @param array $array
     * @param bool $normalize
     * @return array
     */
    protected function normalizeOrDenormalizeArray(array $array, $normalize = true)
    {
        $method = $normalize ? 'normalizeKey' : 'denormalizeKey';

        $keys = $this->$method(array_keys($array));

        return array_combine($keys, array_values($array));
    }

    /**
     * Проверяет есть ли у модели мутатор на получение аттрибута
     *
     * @param  string $key
     *
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    /**
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return $this->{'get' . Str::studly($key) . 'Attribute'}($value);
    }

    /**
     * @param $key
     * @param $value
     *
     * @return array|mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * @return array
     */
    public function getCasts()
    {
        if ($this->getIncrementing()) {
            return array_merge([
                strtolower($this->getKeyName()) => $this->keyType,
            ], $this->casts);
        }

        return $this->casts;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    protected function isDateCastable($key)
    {
        return $this->hasCast($key, ['date', 'datetime']);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        return $this->hasCast($key, ['json', 'array', 'object', 'collection']);
    }

    /**
     * @param $key
     *
     * @return string
     */
    protected function getCastType($key)
    {
        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * @param $key
     * @param $value
     *
     * @return \Carbon\Carbon|int|\Kodix\Support\Collection|string
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'money':
                return number_format((float)$value, $this->moneyDecimals, $this->moneyPoint, $this->thousandsSeparator);
            case 'real':
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'array_collection':
                return new BaseCollection($value);
            case 'date':
            case 'datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimeStamp($value);
            default:
                return $value;
        }
    }

    /**
     * Удаляет модели по переданным id;
     *
     * @param  array|int $ids
     * @return int
     */
    public static function destroy($ids)
    {
        // We'll initialize a count here so we will return the total number of deletes
        // for the operation. The developers can then check this number as a boolean
        // type value or get this total count of records deleted for logging, etc.
        $count = 0;

        $ids = is_array($ids) ? $ids : func_get_args();

        $instance = new static;

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = $instance->getKeyName();

        foreach ($instance->where($key, $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     *
     * @throws \Exception
     */
    public function delete()
    {
        if (is_null($this->getKeyName())) {
            throw new Exception('No primary key defined on model.');
        }

        if ($this->exists) {

            $this->performDeleteOnModel();

            $this->exists = false;

            // Once the model has been deleted, we will fire off the deleted event so that
            // the developers may hook into post-delete operations. We will then return
            // a boolean true as the delete is presumably successful on the database.

            return true;
        }
    }
    
    protected function performDeleteOnModel()
    {
        $this->setKeysForSaveQuery($this->newBuilder())->delete();
    }
    
    

    /**
     * @param $value
     *
     * @return int
     */
    protected function asTimeStamp($value)
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value);
    }

    /**
     * @param $value
     * @param bool $asObject
     *
     * @return string
     */
    protected function fromJson($value, $asObject = false)
    {
        return json_decode($value, !$asObject);
    }

    /**
     * Клонирует модель в новый, не существующий экземпляр
     *
     * @param array|null $except
     *
     * @return mixed
     */
    public function replicate(array $except = null)
    {
        $defaults = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        $except = $except ? array_unique(array_merge($except, $defaults)) : $defaults;

        $attributes = Arr::except($this->attributes, $except);

        $instance = new static;

        $instance->setRawAttributes($attributes);

        return $instance->setRelations($this->relations);
    }

    /**
     * @param $key
     * @param null $types
     *
     * @return bool
     */
    public function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array)$types, true) : true;
        }

        return false;
    }

    /**
     * Получает все записи таблицы текущей модели
     *
     * @param array $columns
     *
     * @return \Kodix\Database\Collection|mixed
     */
    public static function all($columns = [])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $instance = new static;

        return $instance->newBuilder()->get($columns);
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function setFiles($files)
    {
        $this->files = $files;

        return $this;
    }

    public function setFile($key, $value)
    {
        $this->files[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     *
     * @return array|string
     */
    public function denormalizeKey($key)
    {
        return $this->normalizeOrDenormalizeKey($key, false);
    }

    /**
     * Возвращает нормализованный ключ для типов строка и массив
     *
     * @param array|string $key
     *
     * @return array|string
     */
    public function normalizeKey($key)
    {
        return $this->normalizeOrDenormalizeKey($key, true);
    }

    /**
     * @param $key
     * @param $normalize
     *
     * @return array|string
     */
    protected function normalizeOrDenormalizeKey($key, $normalize)
    {
        if (is_string($key)) {
            return $this->manipulatePrefix($key, $normalize);
        }

        //Значит передан массив ключей и мы вернем массив с измененными ключами
        if (is_array($key)) {
            return array_map(function ($item) use ($normalize) {
                return $this->manipulatePrefix($item, $normalize);
            }, $key);
        }

        throw new InvalidArgumentException('Key must be string or array.');
    }

    /**
     * Подставляет префикс к переданному ключу, выполняя нужные проверки.
     *
     * @param $key
     * @param $prefix
     *
     * @return string
     */
    protected function manipulatePrefix($key, $append = true)
    {
        return $append ? $this->addPrefix($key) : $this->removePrefix($key);
    }

    /**
     * @param $key
     *
     * @return string
     */
    protected function addPrefix($key)
    {
        // У битрикса все в верхнем регистре.
        $key = strtolower($key);

        if (!in_array($key, $this->getWithoutPrefix()) && !$this->isPrefixed($key)) {
            $key = $this->prefix . $key;
        }

        return strtoupper($key);
    }

    /**
     * @param $key
     *
     * @return string
     */
    protected function removePrefix($key)
    {
        $key = strtolower($key);

        if (!in_array($key, $this->getWithoutPrefix()) && $this->isPrefixed($key)) {
            $prefixLength = Str::length($this->prefix);

            $key = substr($key, $prefixLength);
        }

        return $key;
    }

    /**
     * @return array
     */
    public function getWithoutPrefix()
    {
        return array_map('strtolower', array_merge((array)$this->withoutPrefix, [$this->getKeyName()]));
    }

    /**
     * @param $key
     * @param null $prefix
     *
     * @return bool
     */
    protected function isPrefixed($key, $prefix = null)
    {
        $prefix = $prefix ?: $this->prefix;

        return Str::startsWith($key, $prefix);
    }

    /**
     * Возвращает дату в виде строки
     *
     * @param \DateTime $date
     *
     * @return string
     */
    protected function serializeDate(DateTime $date)
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Начинает запрос с выбора отношений модели.
     *
     * @param  array|string $relations
     *
     * @return \Kodix\Database\Highload\Builder
     */
    public static function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $instance = new static;

        return $instance->newBuilder()->with($relations);
    }

    /**
     * @return array
     */
    public static function getPreloadedRelations()
    {
        return static::$preloadedRelations;
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param  string $related
     * @param  string $through
     * @param  string|null $firstKey
     * @param  string|null $secondKey
     * @param  string|null $localKey
     *
     * @return \Kodix\Database\Relations\HasManyThrough
     */
    public function hasManyThrough(
        $related,
        $through,
        $firstKey = null,
        $secondKey = null,
        $localKey = null
    )
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();

        $secondKey = $secondKey ?: $through->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new HasManyThrough((new $related)->newBuilder(), $this, $through, $firstKey, $secondKey, $localKey);
    }

    /**
     * Загружает отношения в модель.
     *
     * @param  array|string $relations
     *
     * @return $this
     */
    public function load($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $query = $this->newBuilder()->with($relations);

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Определяет обратное отношение "один к одному".
     *
     * @param $related
     * @param null $foreignKey
     * @param null $otherKey
     * @param null $relation
     *
     * @return \Kodix\Database\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // Если название отношение не было передано, мы используем debug backtrace чтобы получить
        // имя вызываемого метода и будем считать это как имя отношения в большинстве случаев.
        // Это как раз то что нам и было нужно для использования отношений.
        if (is_null($relation)) {
            list($current, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        // Если внешний ключ не передан, мы используем название метода и попытаемся угадать
        // название внешнего ключа, используя метод отношения, который будет объединен
        // с постфиксом "_id", что должно быть максимально приближено к реальному имени
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_id';
        }

        $foreignKey = $this->normalizeKey($foreignKey);

        $instance = new $related;

        $builder = $instance->newBuilder();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($builder, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * @param $related
     * @param null $foreignKey
     * @param null $otherKey
     * @param null $relation
     *
     * @return \Kodix\Database\Relations\BelongsToMany
     */
    public function belongsToMany($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // Если название отношение не было передано, мы используем debug backtrace чтобы получить
        // имя вызываемого метода и будем считать это как имя отношения в большинстве случаев.
        // Это как раз то что нам и было нужно для использования отношений.
        if (is_null($relation)) {
            list($current, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        // Если внешний ключ не передан, мы используем название метода и попытаемся угадать
        // название внешнего ключа, используя метод отношения, который будет объединен
        // с постфиксом "_id", что должно быть максимально приближено к реальному имени
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_id';
        }

        $foreignKey = $this->normalizeKey($foreignKey);

        $instance = new $related;

        $builder = $instance->newBuilder();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsToMany($builder, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Устанавливает связь один-к-одному между моделями
     *
     * @param $related
     * @param null $foreignKey
     * @param null $localKey
     *
     * @return \Kodix\Database\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $related = new $related;

        return new HasOne($related->newBuilder(), $this, $foreignKey, $localKey);
    }

    /**
     * Устанавливает связь один-ко-многим между моделями
     *
     * @param $related
     * @param null $foreignKey
     * @param null $localKey
     *
     * @return \Kodix\Database\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        $related = new $related;

        return new HasMany($related->newBuilder(), $this, $foreignKey, $localKey);
    }

    /**
     * Возвращает модель в виде массива
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        return array_merge($attributes, $this->relationsToArray(), $this->getLoadedFiles());
    }

    protected function getLoadedFiles()
    {
        $loaded = [];
        foreach ($this->getFiles() as $key => $file) {
            if(is_string($key)) {
                $loaded[$key] = $file;
            }
        }

        return $loaded;
    }

    /**
     * Проверяет существование переданного сдвига
     *
     * @param mixed $offset
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Получает значение для переданного сдвига
     *
     * @param mixed $offset
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Устанавливает значение сдвига
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Удаляет элемент из объекта
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * Конвертирует объект модели в JSON
     *
     * @param int $options
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Конвертирует объект модели во что-то JSON-подобное
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Динамическое получение свойств модели
     *
     * @param $key
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Динамическое создание атрибута модели
     *
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Проверяеет сущствование атрибута в модели
     *
     * @param $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return !is_null($this->getAttribute($key));
    }

    /**
     * Удаляет атрибут модели
     *
     * @param $key
     */
    public function __unset($key)
    {
        unset($this->attributes[$this->normalizeKey($key)]);
    }

    /**
     * Динамический вызов методов
     *
     * @param $method
     * @param $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $builder = $this->newBuilder();

        return call_user_func_array([$builder, $method], $parameters);
    }

    /**
     * Динамический вызов статических методов
     *
     * @param $method
     * @param $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Возвращает объект как строковое представление
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }
}