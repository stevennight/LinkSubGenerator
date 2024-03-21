<?php

namespace app\controllers;

use app\services\sub\SubService;
use Yii;
use yii\web\Controller;

class SubController extends Controller
{
    public function actionIndex()
    {
        $data = Yii::$app->request->get();
        $res = (new SubService())->dataIndexProvider($data);
        Yii::$app->response->headers->set('Content-Type', 'text/plain');
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->content = $res;
    }
}