<?php
namespace numbervine\miscutils;

use Yii;
// use yii\helpers\VarDumper;
// use yii\helpers\ArrayHelper;

class RestApiUtils
{
  public static function packageContentIntoResponse($success, $output, $msg=null, $errors=null) {
    $result = [
      'success' => $success,
      'message' => $msg,
      'errors' => $errors,
      'code' => 401
    ];

    if ($success) {
      $_success_msg = $msg ? $msg : 'success';
      $_errors = null;
      $result = [
        'success' => $success,
        'message' => $_success_msg,
        'errors' => $_errors,
        'code' => 200
      ];
      if ($output) {
        $result['output'] = $output;
      }
    }

    $response = \Yii::createObject([
        'class' => 'yii\web\Response',
        'format' => \yii\web\Response::FORMAT_JSON,
        'data' => $result
    ]);

    $headers = $response->headers;
    $headers->add('Access-Control-Allow-Origin', '*');
    $headers->add('Access-Control-Allow-Credentials', true);

    return $response;
  }

  /**
   * Lists all dataprovider models.
   * @return mixed
   */
  public static function packageDataProviderIntoResponse($dataProvider)
  {
    $success = true;
    $content = null;
    $msg = null;
    $errors = null;
    try {
      $content = $dataProvider->getModels();
    } catch (Exception $ex) {
      $success = false;
      $msg = 'error reading assigned tasks';
      $errors = $ex->getMessage();
    }

    return self::packageContentIntoResponse($success,$content,$msg,$errors);
  }

}
