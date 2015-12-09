<?php

namespace app\rest;

use app\models\Test;

class TestApi extends CApi
{

    protected function getModelName()
    {
        return Test::className();
    }

}
