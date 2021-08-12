<?php

/**
 * craftexporter plugin for Craft CMS 3.x
 *
 * plugin’s package description
 *
 * @link      wave2web.com
 * @copyright Copyright (c) 2021 Keshav
 */

namespace keshavsharma\craftexporter;


use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\TemplateEvent;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\models\Section;
use craft\events\RegisterElementExportersEvent;

use yii\base\Event;

use craft\web\View;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://docs.craftcms.com/v3/extend/
 *
 * @author    Keshav
 * @package   Craftexporter
 * @since     1
 *
 */
class Craftexporter extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Craftexporter::$plugin
     *
     * @var Craftexporter
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public $hasCpSettings = false;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Craftexporter::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_EXPORTERS,
            function (RegisterElementExportersEvent $event) {
                $event->exporters[] = Customexporter::class;
            }
        );
        $filterSectionType = Section::TYPE_SINGLE;
        $segments = Craft::$app->getRequest()->segments;
        if (count($segments) > 1 && $segments[1] === 'singles') {
            $filteredSections = ArrayHelper::where(\Craft::$app->sections->getAllSections(), 'type', $filterSectionType);
        } else if (count($segments) > 1 && $segments[1] !== 'singles') {
            $filterSectionType = $segments[1];
            $filteredSections = ArrayHelper::where(\Craft::$app->sections->getAllSections(), 'handle', $filterSectionType);
        }


        $fieldNamesList = [];

        if(!empty($filteredSections)) {
            $qry = Entry::find()->sectionId(ArrayHelper::getColumn($filteredSections, 'id'));

            $anyEntry = $qry->one();
            if (!empty($anyEntry)) {
                $fields = $anyEntry->getFieldLayout()->getFields();
    
                foreach ($fields as $field) {
                    $fieldNamesList[$field->handle] = $field->name;
                }
            }
        }
        
        $fieldNamesList = json_encode($fieldNamesList);
        // $section = Craft::$app->sections->getSectionById(1);
        // $qry =  Entry::find()->where('type = :type', array(':type' => "Assets"))->all();
        // print_r( Craft::$app->getRequest()); die;
        // $fields = Craft.entries.section('rentalsBulletin').getEntryTypes().first.getFieldLayout().getFields();
        // print_r($fields); die;
        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'craftexporter',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
        $app =  Craft::$app;
        $segments = Craft::$app->getRequest()->segments;
        
        if ($app->getRequest()->getIsCpRequest() && count($segments) > 0 && $segments[0] === 'entries') {
            if($app->getRequest()->isAjax ){
                echo $fieldNamesList;
                die;
            }
             
            $js = <<<EOT
    
            \$(function() {
                var allAvailFieldsForExport = $fieldNamesList;
                \$('#export-btn').click( function() {
                    var formSubmit = \$('.export-form .submit');
                    if($('.field_exprt_fields_list').length != 0) {
                        \$('.field_exprt_fields_list').html('');
                    }
                    var limitField = Craft.ui.createTextField({
                        label: Craft.t('app', 'Offset'),
                        placeholder: Craft.t('app', '0'),
                        type: 'number',
                        class: 'offsetCust',
                        min: 0
                    }).insertBefore(formSubmit);
    
                    \$('<div class="field field_exprt_fields_list" ><div class="heading"><label style=" display: block;">Select Fields</label></div> </div>').insertBefore(formSubmit);
                    
                    setupCheckboxes(allAvailFieldsForExport);
                }); 
                


                Craft.elementIndex.on('registerViewParams', function(e, p) {
                    var offset = \$('.offsetCust').val() && \$('.offsetCust').val().trim() != '' ? \$('.offsetCust').val() : 0;
                    var selectedFields = [];
                    $('[name="export_fields[]"]:checked').each(function(){
                        selectedFields.push(this.value);
                    });
                    selectedFields = selectedFields.length > 0 ? selectedFields.join(',') : '';
                    var limit = offset + ',' +  selectedFields ;
                    e.params.paginated = limit;
                
                
                    return e;
                }); 

                Craft.elementIndex.on('updateElements', function(e, p) {
                     
                     \$.get(window.location.href, function(res){
                        allAvailFieldsForExport = JSON.parse( res );
                        \$('.field_exprt_fields_list').html('');
                        setupCheckboxes(allAvailFieldsForExport);
                     });
                }); 
            }); 
        
            function setupCheckboxes(allAvailFieldsForExport){
                console.log(allAvailFieldsForExport);
                for(var field in allAvailFieldsForExport){
                    var htm = '<div class="_chk_field"><label >'+ Craft.escapeHtml(allAvailFieldsForExport[field])+' <input type="checkbox" value="'+ field +'" class="checkbox_exp" name="export_fields[]" checked="checked"></label></div>';
                    $('.field_exprt_fields_list').append(htm);
                    
                }
            }
            EOT;

            $css = <<<EOT
            .field_exprt_fields_list {
                max-height: 200px; 
                overflow: auto; 
            }
            .field.field_exprt_fields_list input{
                float: left;
                margin: 4px 5px 0;
            }
            
            .field.field_exprt_fields_list label{
                display: block;
            }
            EOT;
             
            $app->getView()->registerJs($js);
            $app->getView()->registerCss($css);
        }
    }
    // Protected Methods
    // =========================================================================

}
