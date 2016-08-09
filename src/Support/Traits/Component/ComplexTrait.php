<?php


namespace Kodix\Support\Traits\Component;

use CComponentEngine;

/**
 * This trait makes complex class easier to write
 * You just need to change setUrlTemplates404 method with your paths
 *
 * Class ComplexTrait
 * @package Malyusha\Helpers\Traits
 *
 * @property array $arParams
 */
trait ComplexTrait
{
    public $page = '';
    public $defaultPage = 'list';
    /**
     * @var CComponentEngine instance
     */
    public $engine;
    public $urlTemplates;
    public $variables = [];
    public $componentVariables;
    public $variablesAliases = [];
    public $urlTemplates404 = [];
    public $variableAliases404 = [];

    public function setUrlTemplates404()
    {
        $this->urlTemplates404 = [
            "detail" => $this->arParams["SEF_URL_TEMPLATES"]["detail"],
            "section" => $this->arParams["SEF_URL_TEMPLATES"]["section"],
        ];
    }

    public function boot()
    {
        $this->setUrlTemplates404();
        $this->startEngine();
        $this->makeTemplates();
        $this->makeAliases();
        $this->setPage();
        $this->checkPage();
        $this->makeResult();
    }

    public function startEngine()
    {
        $this->engine = new CComponentEngine($this);

        if(\Bitrix\Main\Loader::includeModule('iblock')) {
            $this->engine->addGreedyPart("#SECTION_CODE_PATH#");
            $this->engine->setResolveCallback(["CIBlockFindTools", "resolveComponentEngine"]);
        }
    }

    public function setPage()
    {
        $this->page = $this->engine->guessComponentPath(
            $this->arParams["SEF_FOLDER"],
            $this->urlTemplates,
            $this->variables
        );
    }

    public function checkPage()
    {
        if(!$this->page) {
            if($this->arParams['SHOW_404'] == 'Y')
                show404();
            else
                $this->page = $this->defaultPage;
        }
        CComponentEngine::InitComponentVariables($this->page, $this->componentVariables, $this->variablesAliases, $this->variables);
    }

    public function makeTemplates()
    {
        $this->urlTemplates = CComponentEngine::MakeComponentUrlTemplates($this->urlTemplates404, $this->arParams["SEF_URL_TEMPLATES"]);
    }

    public function makeAliases()
    {
        $this->variablesAliases = CComponentEngine::MakeComponentVariableAliases($this->variableAliases404, $this->arParams["VARIABLE_ALIASES"]);
    }

    public function makeResult()
    {
        $this->arResult = [
            "FOLDER" => $this->arParams["SEF_FOLDER"],
            "URL_TEMPLATES" => $this->urlTemplates,
            "VARIABLES" => $this->variables,
            "ALIASES" => $this->variablesAliases,
        ];
    }
}