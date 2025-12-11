<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

class IpController extends Controller
{
    public function actionDns() {
        $host = Yii::$app->request->get('host');
        if (empty($host)) {
            return;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return;
        }

        $this->response->content = $ip;
        $this->response->send();
    }
}