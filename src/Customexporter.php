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
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementExporter;
use craft\base\Field;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;

class Customexporter extends ElementExporter
{
    public static function displayName(): string
    {
        return 'My Custom Exporter';
    }

    public function export(ElementQueryInterface $query): array
    {
        $results = [];
        $fields = Craft::$app->getRequest()->bodyParams['paginated'];
        $fields = explode(',', $fields);
        $paginOffset =  array_shift($fields);

        
        $paginLimit =  100;
        if (isset(Craft::$app->getRequest()->bodyParams['criteria']['limit'])) {
            $paginLimit =  Craft::$app->getRequest()->bodyParams['criteria']['limit'];
        }

        // Eager-load the entries related via the relatedEntries field
        /** @var ElementQuery $query */
        $query->with(['relatedEntries']);
        $query->offset($paginOffset);
        $query->limit($paginLimit);



        $eagerLoadableFields = [];
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($field instanceof EagerLoadingFieldInterface) {
                $eagerLoadableFields[] = $field->handle;
            }
        }

        $data = [];

        /** @var ElementQuery $query */
        $query->with($eagerLoadableFields);

        foreach ($query->each() as $element) {
            // Get the basic array representation excluding custom fields
            $attributes = array_flip($element->attributes());
            if (($fieldLayout = $element->getFieldLayout()) !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    unset($attributes[$field->handle]);
                }
            }
            $elementArr = $element->toArray(array_keys($attributes));
            if ($fieldLayout !== null) {
                foreach ($fieldLayout->getFields() as $field) {
                    $value = $element->getFieldValue($field->handle);
                    $elementArr[$field->handle] = $field->serializeValue($value, $element);
                }
            }
            $modDataObj = ['title' => $elementArr['title'] , 'status' => $elementArr['status'], 'url' => $elementArr['url']];
            foreach ($fields as $fieldSlug) {
                $modDataObj[$fieldSlug] = $elementArr[$fieldSlug];
            }
            $data[] = $modDataObj;
        }

        
 

        return $data;
    }
}
