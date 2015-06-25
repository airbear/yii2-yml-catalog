<?php
namespace pastuhov\ymlcatalog;

use pastuhov\ymlcatalog\models\BaseModel;
use pastuhov\ymlcatalog\models\Category;
use pastuhov\ymlcatalog\models\LocalDeliveryCost;
use pastuhov\ymlcatalog\models\Shop;
use Yii;
use pastuhov\FileStream\BaseFileStream;
use yii\base\Exception;

/**
 * Yml генератор каталога.
 *
 * @package pastuhov\ymlcatalog
 */
class YmlCatalog
{
    protected $handle;
    protected $shopClass;
    protected $localDeliveryCostClass;
    protected $categoryClass;
    protected $offerClass;
    protected $date;

    public function __construct(
        BaseFileStream $handle,
        $shopClass,
        $localDeliveryCostClass,
        $categoryClass,
        $offerClass,
        $date = null
    ) {
        $this->handle = $handle;
        $this->shopClass = $shopClass;
        $this->localDeliveryCostClass = $localDeliveryCostClass;
        $this->categoryClass = $categoryClass;
        $this->offerClass = $offerClass;
        $this->date = $date;
    }

    public function generate()
    {
        $date = $this->getDate();

        $this->write(
            '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL .
            '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . PHP_EOL .
            '<yml_catalog date="' . $date . '">' . PHP_EOL
        );

        $this->writeTag('shop');
        $this->writeModel(new Shop(), new $this->shopClass());
        $this->writeTag('categories');
        $this->writeEachModel($this->categoryClass);
        $this->writeTag('/categories');
        $this->writeModel(new LocalDeliveryCost(), new $this->localDeliveryCostClass());
        $this->writeTag('/shop');

        $this->write('</yml_catalog>');
    }

    /**
     * @return null
     */
    protected function getDate()
    {
        $date = $this->date;

        if ($date === null) {
            $date = Yii::$app->formatter->asDatetime(new \DateTime(), 'php:Y-m-d H:i');
        }

        return $date;
    }

    protected function write($string)
    {
        $this->handle->write($string);
    }

    protected function writeTag($string)
    {
        $this->write('<' . $string . '>' . PHP_EOL);
    }

    protected function writeModel(BaseModel $model, $valuesModel)
    {
        $attributes = [];
        foreach ($model->attributes() as $attribute) {
            $methodName = 'get' . ucfirst($attribute);
            $attributeValue = $valuesModel->$methodName();

            $attributes[$attribute] = $attributeValue;
        }

        $model->load($attributes, '');

        if (!$model->validate()) {
            throw new Exception('Model values is invalid ' . serialize($model->getErrors()));
        }

        $string = $model->getYml();

        $this->write($string);
    }

    /**
     * @param string $modelClass
     */
    protected function writeEachModel($modelClass)
    {
        /* @var \yii\db\ActiveQuery $query */
        $query = $modelClass::findYml();

        foreach ($query->batch(100) as $models) {
            foreach ($models as $model) {
                $this->writeModel(new Category(), $model);
            }
        }
    }
}
