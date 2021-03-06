<?php

namespace deka6pb\autoparser\controllers;

use deka6pb\autoparser\components\CollectorService;
use deka6pb\autoparser\components\PostingService;
use deka6pb\autoparser\models\Posts;
use yii\web\Controller;
use Yii;

class DefaultController extends Controller
{
    public function actionIndex() {
        if(Posts::countNewPosts() < $this->module->getMaxCountPosting()) {
            $collector = new CollectorService($this->module->getProviders());
            $collector->run();
        }

        $posting = new PostingService($this->module->getConsumers());
        $posting->run();
    }
}
