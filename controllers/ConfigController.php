<?php

namespace app\controllers;

use app\services\config\ConfigService;
use yii\web\Controller;

class ConfigController extends Controller
{
    public function actionNode() {
        if (\Yii::$app->request->isPost) {
            $data = \Yii::$app->request->post();
            $res = (new ConfigService())->dataNodeProvider($data);
            return $res;
        }

        return $this->render('node');
    }

    public function actionRelayList() {
        if (\Yii::$app->request->isPost) {
            $data = \Yii::$app->request->post();
            $res = (new ConfigService())->dataRelayListProvider($data);
            return $res;
        }

        return $this->render('relayList');
    }
}