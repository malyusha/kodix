<?php

namespace Kodix\Database\Highload;

use CFile;
use Bitrix\Main\Entity\Base;
use Bitrix\Main\Entity\DataManager;
use Closure;
use InvalidArgumentException;
use Kodix\Database\Model;
use Kodix\Database\Relations\Relation;
use Kodix\Support\Arr;
use Kodix\Support\Collection;
use Kodix\Support\Str;

class Builder
{
    /**
     * Выбираемые поля таблицы
     *
     * @var array $select
     */
    public $select = [];

    /**
     * Фильтр запроса
     *
     * @var array $filter
     */
    public $filter = [];

    /**
     * Параметр группировки запроса.
     *
     * @var array
     */
    public $group = [];

    /**
     * Порядок сортировки
     *
     * @var array $order
     */
    public $order = [];

    /**
     * Лимит записей выборки
     *
     * @var int $limit
     */
    public $limit;

    /**
     * Смещение для выборки
     *
     * @var int $offset
     */
    public $offset;

    /**
     * Модель текущего объекта
     *
     * @var Model $model
     */
    protected $model;

    /**
     * Первичный ключ модели
     *
     * @var string|int $modelPrimary
     */
    protected $modelPrimary;

    /**
     * Ошибки, произошедшие при выполнении запросов
     *
     * @var array $errors
     */
    protected $errors = [];

    /**
     * Экземляр сущности таблицы Bitrix
     *
     * @var DataManager $manager
     */
    protected $manager;

    /**
     * Экземпляр класса файлов
     *
     * @var \CFile
     */
    protected $fileLoader;

    /**
     * Файлы, которые должны быть загружены
     *
     * @var array
     */
    protected $files = [];

    /**
     * Сформированные параметры запроса
     *
     * @var array $parameters
     */
    protected $parameters = [];

    /**
     * Отношения, которые загружаются сразу.
     *
     * @var array
     */
    protected $eagerLoad = [];

    /**
     * Операторы сравнения, используемые Bitrix-ом
     *
     * @var array $operators
     */
    protected $operators = [
        '>', '<', '<=', '=%', '%=', '>=', '=!', '=', '!', '~',
    ];

    /**
     * Операторы, которые по умолчанию являются сравнением в Bitrix
     *
     * @var array $defaultEqualsOperators
     */
    protected $defaultEqualsOperator = '=';

    /**
     * Алиасы операторов выборки
     *
     * @var array $mutators
     */
    protected $mutators = [
        '!=' => '!',
    ];

    protected $logicAdded = false;

    protected $logicStatements = ['OR', 'AND'];

    /**
     * Таблица из которой происходит выборка
     *
     * @var string $from
     */
    public $from;

    /**
     * Query constructor.
     *
     * @param DataManager $manager
     */
    public function __construct(DataManager $manager, CFile $fileLoader)
    {
        $this->manager = $manager;
        $this->fileLoader = $fileLoader;
    }

    /**
     * Сливает собственные отношения и те, которые должны быть всегда загружены в модель
     */
    protected function mergeRelations()
    {
        $relations = $this->model->getPreloadedRelations();

        if (count($relations) > 0) {
            $preloaded = $this->parseWithRelations($this->model->getPreloadedRelations());

            $this->eagerLoad = array_merge($preloaded, $this->eagerLoad);
        }

        return $this;
    }

    /**
     * @param $table
     *
     * @return $this
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return $this
     */
    public function select($columns = [])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->select = $this->model->normalizeKey($columns);

        return $this;
    }

    /**
     * @param $column
     *
     * @return $this
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->select = array_merge((array)$this->select, $this->model->normalizeKey($column));

        return $this;
    }

    /**
     * @param $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $column = $this->model->normalizeKey($column);
        if (is_array($column)) {
            $this->order = $column;
        } else {
            $this->order[$column] = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        }

        return $this;
    }

    /**
     * @param $column
     * @param null $operator
     * @param null $value
     *
     * @return Builder
     */
    public function where($column, $operator = null, $value = null)
    {
        if (is_array($column)) {
            return $this->addArrayOfColumns($column);
        }

        if (func_num_args() == 2) {
            list($value, $operator) = [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination');
        }

        return $this->addWhereStatement($column, $operator, $value);
    }

    /**
     * Добавляет логическое "ИЛИ" в параметры фильтрации.
     *
     * @param $column
     * @param null $operator
     * @param null $value
     * @return \Kodix\Database\Highload\Builder
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_array($column)) {
            $column = $this->getNormalizedColumns($column);
        } elseif (func_num_args() == 2) {
            list($value, $operator) = [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination');
        }

        if (!is_array($column)) {
            $field = $this->getColumnWithPrefixedOperator($column, $operator);
            $column = $this->getNormalizedColumns([$field => $value]);
        }


        return $this->addLogicWhereStatement($column, 'OR');
    }

    /**
     * @param $columns
     * @return array
     */
    protected function getNormalizedColumns(array $columns)
    {
        $result = [];

        foreach ($columns as $column => $value) {
            if(preg_match('/^[a-z]/', $column)) {
                $column = $this->model->normalizeKey($column);
                $prefixed = $this->getColumnWithPrefixedOperator($column, $this->defaultEqualsOperator);
                $result[$prefixed] = $value;

                continue;
            }

            foreach ($this->operators as $operator) {
                if (strpos($column, $operator) !== false) {
                    $column = $this->model->normalizeKey(str_replace($operator, '', $column));
                    $prefixed = $this->getColumnWithPrefixedOperator($column, $operator);
                    $result[$prefixed] = $value;

                    break;
                }


            }
        }

        return $result;
    }

    protected function addLogicWhereStatement(array $fields, $logic)
    {
        if ($this->logicAdded) {
            return $this->injectLogicStatement($fields, $logic);
        }

        $this->filter[] = ['LOGIC' => strtoupper($logic), $fields];

        $this->logicAdded = true;

        return $this;
    }

    protected function injectLogicStatement(array $fields, $logic)
    {
        if ($this->invalidLogic($logic)) {
            throw new InvalidArgumentException(
                sprintf('Invalid logic operator %s in logic statement.', $logic)
            );
        }

        // Мы пройдемся по всему фильтру и найдем выражение, которое будет означать, что
        // мы имеем дело с фильтром логики.
        foreach ($this->filter as $key => $field) {

            // Если поле - массив, и первый ключ этого массива является выражением логики,
            // то это то, что нам нужно.
            if (is_array($field) && reset($field) === $logic) {
                // Мы добавим в фильтр поля, которые должны содержать логику.
                $this->filter[$key][] = $this->getNormalizedColumns($fields);
            }
        }

        return $this;
    }

    protected function invalidLogic($logic)
    {
        return !in_array($logic, $this->logicStatements);
    }

    /**
     * @param string $column
     *
     * @return Builder
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * @param string $column
     *
     * @return Builder
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function limit($value)
    {
        if ($value > 0) {
            $this->limit = $value;
        }

        return $this;
    }

    /**
     * @param $value
     *
     * @return Builder
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * @param $value
     *
     * @return Builder
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * @param $page
     * @param int $perPage
     *
     * @return Builder
     */
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * @param $value
     *
     * @return $this
     */
    public function offset($value)
    {
        if ($value > 0) {
            $this->offset = $value;
        }

        return $this;
    }

    /**
     * @param $id
     * @param array $columns
     *
     * @return Model|null
     */
    public function find($id, $columns = [])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        $this->where($this->model->getKeyName(), '=', $id);

        return $this->first($columns);
    }

    /**
     * @param array $ids
     * @param array $columns
     *
     * @return Collection
     */
    public function findMany(array $ids, $columns = [])
    {
        if (count($ids) === 0) {
            return $this->model->newCollection();
        }

        $this->where($this->model->getKeyName(), '=', $ids);

        return $this->get($columns);
    }

    /**
     * @param array $columns
     *
     * @return Model|null
     */
    public function first($columns = [])
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * @param $column
     * @param $operator
     * @param $value
     *
     * @return $this
     */
    protected function addWhereStatement($column, $operator, $value)
    {
        $column = $this->model->normalizeKey($column);
        $compiledColumn = $this->getColumnWithPrefixedOperator($column, $operator);

        $this->filter[$compiledColumn] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function freshLogic()
    {
        $this->logicStatements = false;

        return $this;
    }

    /**
     * Возвращает название столбца с префиксом, пропуская префикс через проверку подмены (алиасов)
     *
     * @param $column
     * @param $operator
     *
     * @return string
     */
    protected function getColumnWithPrefixedOperator($column, $operator)
    {
        $operator = array_key_exists($operator, $this->mutators) ? $this->mutators[$operator] : $operator;

        return $operator . $column;
    }

    /**
     * @param $operator
     *
     * @return bool
     */
    protected function isDefaultEqual($operator)
    {
        return $operator == $this->defaultEqualsOperator;
    }

    /**
     * @param $columns
     *
     * @return $this
     */
    protected function addArrayOfColumns($columns)
    {
        $this->filter = array_merge((array)$this->filter, $this->getNormalizedColumns($columns));

        return $this;
    }

    /**
     * @param $operator
     * @param $value
     *
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return is_null($value) && $isOperator && !$this->isDefaultEqual($operator);
    }

    /**
     * Устанавливает отношения, которые должны быть загружены сразу.
     *
     * @param  mixed $relations
     * @return $this
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $eagers = $this->parseWithRelations($relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagers);

        return $this;
    }

    /**
     * @return bool
     */
    protected function hasFiles()
    {
        return count($this->getFiles()) > 0;
    }

    /**
     * @return array
     */
    protected function getFiles()
    {
        return array_flip($this->getModel()->getFiles());
    }

    /**
     * @param array $files
     * @return $this|bool
     */
    public function withFiles(array $files)
    {
        if (!$this->model->exists) {
            return false;
        }

        $this->files = array_merge($this->files, $files);

        return $this;
    }

    /**
     * @param array $relations
     * @return array|mixed
     */
    protected function parseWithRelations(array $relations)
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $f = function () {
                    //
                };

                list($name, $constraints) = [$constraints, $f];
            }

            $results = $this->parseNestedWith($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Парсит вложенные отношения в переданной строке отношений.
     *
     * @param $name
     * @param $results
     * @return mixed
     */
    protected function parseNestedWith($name, $results)
    {
        $progress = [];

        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                    //
                };
            }
        }

        return $results;
    }

    /**
     * @param array $columns
     *
     * @return \Kodix\Database\Collection
     */
    public function get($columns = [])
    {
        $models = $this->getModels($columns);

        if (count($models) > 0) {
            $models = $this->eagerLoadRelations($models);
            $models = $this->hasFiles() ? $this->eagerLoadFiles($models) : $models;
        }

        return $this->getModel()->newCollection($models);
    }

    /**
     * Создает файл из массива и возвращает id созданной записи
     *
     * @param $file
     *
     * @return int|string
     */
    public function createFile($file)
    {
        if (is_numeric($file) || !$file) {
            return $file;
        }

        return $this->fileLoader->SaveFile($file, '');
    }

    /**
     * Производит загрузку всех отношений в переданные модели.
     *
     * @param array $models
     * @return array|void
     */
    public function eagerLoadRelations(array $models)
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->loadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * @param array $models
     * @return mixed
     */
    public function eagerLoadFiles(array $models)
    {
        $ids = $this->getFilesIds($models);
        if (count($ids) == 0) {
            return $models;
        }

        $filter = ['@ID' => $this->getFilesIds($models)];
        $dbResult = $this->fileLoader->GetList([], $filter);
        $loadedFiles = $this->fetchFiles($dbResult);

        return $this->initFiles($models, $loadedFiles);
    }

    /**
     * @param array $models
     * @param $files
     * @return array
     */
    protected function initFiles(array $models, $files)
    {
        foreach ($models as $model) {
            $modelFiles = [];
            $uploadFolder = $model->getUploadFolder();

            foreach ($this->getFiles() as $file => $number) {
                $fileKey = $model->getAttribute($file);
                if (isset($files[$fileKey])) {
                    $fileData = $files[$fileKey];

                    $modelFiles[$file] = $uploadFolder . $fileData['SUBDIR'] . '/' . $fileData['FILE_NAME'];
                }
            }
            $model->setFiles($modelFiles);
        }

        return $models;
    }

    /**
     * @param $result
     * @return array
     */
    protected function fetchFiles($result)
    {
        $files = [];
        while ($file = $result->Fetch()) {
            $files[$file['ID']] = $file;
        }

        return $files;
    }

    /**
     * @param array $models
     * @return array
     */
    protected function getFilesIds(array $models)
    {
        $ids = [];
        foreach ($this->getFiles() as $file => $number) {
            foreach ($models as $model) {
                if ($fileId = $model->getAttribute($file)) {
                    $ids[] = $fileId;
                }
            }
        }

        return $ids;
    }

    /**
     * Загружает отношение в переданные модели.
     *
     * @param array $models
     * @param $name
     * @param \Closure $constraints
     */
    protected function loadRelation(array $models, $name, Closure $constraints)
    {
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        call_user_func($constraints, $relation);

        $models = $relation->initRelation($models, $name);

        $results = $relation->getEager();

        return $relation->match($models, $results, $name);
    }


    /**
     * @param $name
     * @return Relation
     */
    public function getRelation($name)
    {
        $relation = Relation::noConstraints(function () use ($name) {
            return $this->getModel()->$name();
        });

        $nested = $this->nestedRelations($name);

        if (count($nested) > 0) {
            $relation->getBuilder()->with($nested);
        }

        return $relation;
    }

    /**
     * @param $relation
     * @return array
     */
    public function nestedRelations($relation)
    {
        $nested = [];

        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNested($name, $relation)) {
                $nested[substr($name, strlen($relation . '.'))] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * @param $name
     * @param $relation
     * @return bool
     */
    public function isNested($name, $relation)
    {
        $dots = Str::contains($name, '.');

        return $dots && Str::startsWith($name, $relation . '.');
    }

    /**
     * @param $columns
     *
     * @return array
     */
    protected function getResults($columns = [])
    {
        $original = $this->select;

        if (empty($original)) {
            $this->select($columns);
        }

        $results = $this->runSelect();

        $this->select = $original;

        return $results->fetchAll();
    }

    /**
     * @param array $columns
     *
     * @return \Kodix\Database\Collection
     */
    public function getModels($columns = [])
    {
        $results = $this->getResults($columns);

        return $this->model->hydrate($results)->all();
    }

    /**
     * @return \Bitrix\Main\DB\Result
     * @throws \Bitrix\Main\ArgumentException
     */
    protected function runSelect()
    {
        $parameters = $this->getPreparedParameters();

        return $this->manager->getList($parameters);
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int $count
     * @param  callable $callback
     *
     * @return  bool
     */
    public function chunk($count, callable $callback)
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if (call_user_func($callback, $results) === false) {
                return false;
            }

            $page++;

            $results = $this->forPage($page, $count)->get();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return (bool)$this->count();
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->manager->getCount($this->filter);
    }

    /**
     * Устанавливает первичный ключ модели
     *
     * @param string|int $primary
     * @return $this
     */
    public function setModelPrimary($primary)
    {
        $this->modelPrimary = $primary;

        return $this;
    }

    /**
     * @param array $values
     *
     * @return bool|int
     * @throws \Exception
     */
    public function insert(array $values)
    {
        if (count($values) === 0) {
            return true;
        }

        $result = $this->manager->add($values);

        if ($result->isSuccess()) {
            return $result->getId();
        }

        $this->errors = $result->getErrorMessages();

        return false;
    }

    /**
     * @param $primary
     * @param array $values
     *
     * @return bool|int
     * @throws \Exception
     */
    public function update(array $values)
    {
        $result = $this->manager->update(
            $this->modelPrimary,
            $this->addUpdatedAtColumn($values)
        );

        if ($result->isSuccess()) {
            return $result->getId();
        }

        $this->errors = $result->getErrors();

        return false;
    }

    /**
     * @param array $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (!$this->model->usesTimestamps()) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        return Arr::add($values, $column, $this->model->freshTimestampString());
    }

    /**
     * @param $primary
     *
     * @return bool
     * @throws \Exception
     */
    public function delete($primary = null)
    {
        if (is_null($primary)) {
            $primary = $this->modelPrimary;
        }

        $result = $this->manager->delete($primary);

        if ($result->isSuccess()) {
            return $result->isSuccess();
        }

        $this->errors = $result->getErrors();

        return false;
    }

    /**
     * @return static
     */
    public function newBuilder()
    {
        return new static($this->manager);
    }

    /**
     * @param array|string $field
     */
    public function groupBy($field)
    {
        $field = is_string($field) ? func_get_args() : $field;

        $this->group = $this->model->normalizeKey($field);

        return $this;
    }

    /**
     * Возвращает подготовленные параметры для выборки
     *
     * @link https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5753
     *
     * @return array
     */ 
    protected function getPreparedParameters()
    {
        if (!empty($this->parameters)) {
            //Параметры закешированы
            return $this->parameters;
        }

        //Закешируем параметры для дальнейших выборок
        foreach (['select', 'filter', 'group', 'order', 'limit', 'offset'] as $field) {
            $property = $this->$field;
            //Тут проверим, есть ли что-либо в параметрах
            //Если нет, то просто идем дальше
            if ((is_array($property) && !empty($property)) || $property) {
                $this->parameters[$field] = $property;
            }
        }

        return $this->parameters;
    }

    /**
     *
     */
    protected function fresh()
    {
        $this->parameters = [];
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param Model $model
     *
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->mergeRelations();

        return $this;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string $method
     * @param  array $parameters
     *
     * @return mixed
     */
    /*public function __call($method, $parameters)
    {
        call_user_func_array([$this->manager, $method], $parameters);

        return $this;
    }*/

}