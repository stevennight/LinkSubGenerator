<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Node</title>
</head>
<body>
<div>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=Yii::$app->request->getCsrfToken()?>" />
        <textarea name="content" style="width: 100%; height: 90%; box-sizing: border-box"></textarea>
        <input type="submit" value="提交">
    </form>
</div>
</body>
</html>
