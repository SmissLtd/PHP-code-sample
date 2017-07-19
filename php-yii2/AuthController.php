<?php

namespace app\controllers;

use Yii;
use yii\web\HttpException;
use yii\helpers\Json;
use yii\helpers\Url;
use app\models\User;
use app\models\UserCode;
use app\models\UserFriend;

class AuthController extends \app\components\Controller
{
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess']
            ]
        ];
    }
    
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => \yii\filters\VerbFilter::className(),
                'actions' => [
                    'register-submit' => ['post'],
                    'login-submit' => ['post'],
                    'logout' => ['post'],
                    'validate-code' => ['post'],
                    'error' => ['get', 'post'],
                    'register-validate' => ['post'],
                    'restore-submit' => ['post'],
                    '*' => ['get']
                ]
            ]
        ];
    }

    public function actionIndex()
    {
        return $this->redirect(Url::to(['/auth/login']));
    }
    
    public function actionRegister()
    {
        return $this->render('register');
    }
    
    public function actionRegisterSubmit()
    {
        $data = Yii::$app->request->post('model');
        $model = new User(['scenario' => 'register']);
        $model->attributes = $data;
        $model->phone_clear = \app\models\ContactPhone::clearPhone($model->phone);
        if (!$model->validate())
        {
            $errors = $model->errors;
            if (isset($errors['phone_clear']))
            {
                if (empty($errors['phone']))
                    $errors['phone'] = [];
                $errors['phone'] = array_merge($errors['phone'], $errors['phone_clear']);
                unset($errors['phone_clear']);
            }
            return Json::encode(['error' => 1, 'data' => $errors]);
        }
        // Save new user
        $model->auth_key = Yii::$app->security->generateRandomString(64);
        $model->is_deleted = false;
        $model->setPassword($model->password);
        $model->role = User::ROLE_USER;
        $model->date_register = date('Y-m-d H:i:s');
        $model->date_action = date('Y-m-d H:i:s');
        $model->save(false);
        // Set code as used
        $code = UserCode::findOne(['code' => $model->code, 'is_deleted' => false]);
        $code->is_used = true;
        $code->used_user_id = $model->_id;
        $code->date_used = date('Y-m-d H:i:s');
        $code->save(false);
        // Create friends
        $friend = new UserFriend();
        $friend->user_id = $model->_id;
        $friend->friend_id = $code->user_id;
        $friend->is_code = true;
        $friend->save(false);
        $friend = new UserFriend();
        $friend->user_id = $code->user_id;
        $friend->friend_id = $model->_id;
        $friend->is_code = true;
        $friend->save(false);
        Yii::$app->user->login($model);
    }
    
    public function actionRegisterValidate()
    {
        $field = trim(Yii::$app->request->post('field', ''));
        $allowedFields = ['name', 'login', 'email', 'password', 'phone', 'city', 'country'];
        if (!in_array($field, $allowedFields))
            throw new HttpException(500, Yii::t('app', 'Указанное поле не существует'));
        $value = Yii::$app->request->post('value', '');
        $model = new User(['scenario' => 'register']);
        $model->attributes = [$field => $value];
        if (!$model->validate([$field]))
            return Json::encode(['error' => 1, 'data' => $model->errors]);
    }
    
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }
    
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest)
            return $this->goHome();
        return $this->render('login');
    }
    
    public function actionLoginSubmit()
    {
        $model = new \app\models\forms\Login();
        $model->remember = false;
        $model->attributes = Yii::$app->request->post('model');
        if (!$model->validate())
            return Json::encode(['error' => 1, 'data' => $model->errors]);
        if ($model->user->is_blocked && (empty($model->user->block_till) || strtotime($model->user->block_till) >= time()))
            throw new HttpException(500, Yii::t('app', 'Ваш аккаунт заблокирован {date} за "{reason}". Заблокировал {blocker}. Окончание блокировки {till}', [
                'date' => Yii::$app->formatter->asDatetime($model->user->block_date, 'medium'),
                'reason' => empty($model->user->block_reason) ? Yii::t('app', 'неизвестно') : $model->user->block_reason,
                'blocker' => $model->user->blocker ? $model->user->blocker->nameOrLogin : Yii::t('app', 'неизвестный администратор'),
                'till' => empty($model->user->blocked_till) ? Yii::t('app', 'никогда') : Yii::$app->formatter->asDate($model->user->blocked_till, 'medium')
            ]));
        if ($model->user->is_blocked)
        {
            $model->user->is_blocked = false;
            $model->user->save(false);
        }
        Yii::$app->user->login($model->user, $model->remember ? 3600*24*30 : 0);
    }
    
    public function actionValidateCode()
    {
        $model = new User(['scenario' => 'register']);
        $model->attributes = Yii::$app->request->post('model');
        if (!$model->validate(['code']))
            return Json::encode(['error' => 1, 'data' => $model->errors]);
        Yii::$app->session->set('code', $model->code);
    }
    
    public function actionRestoreSubmit()
    {
        $data = Yii::$app->request->post('model');
        $email = trim(isset($data['email']) ? $data['email'] : '');
        if (empty($email))
            return Json::encode (['error' => 1, 'data' => ['email' => [Yii::t('app', 'Email обязателен')]]]);
        if (!($user = User::findOne(['email' => $email])))
            return Json::encode (['error' => 1, 'data' => ['email' => [Yii::t('app', 'Пользователь с указанным Email не найден')]]]);
        if ($user->is_deleted)
            return Json::encode (['error' => 1, 'data' => ['email' => [Yii::t('app', 'Пользователь с указанным Email удален')]]]);
        $password = Yii::$app->security->generateRandomString(8);
        $user->setPassword($password);
        $user->save(false);
        Yii::$app->mailer
                ->compose('restore-password', ['password' => $password, 'user' => $user])
                ->setFrom(Yii::$app->params['emailFrom'])
                ->setTo($user->email)
                ->setSubject(Yii::t('app', 'Восстановление пароля'))
                ->send();
        return Yii::t('app', 'Новый пароль успешно отправлен на Ваш E-mail');
    }
    
    public function actionCitySearch($term = '')
    {
        $result = [];
        $term = trim($term);
        if (!empty($term))
        {
            $list = \app\models\City::find()
                    ->where(['and', ['is_deleted' => false], ['like', 'title', $term]])
                    ->orderBy('title')
                    ->all();
            foreach ($list as $item)
                $result[] = ['label' => $item->title, 'value' => $item->title];
        }
        return Json::encode($result);
    }
    
    public function actionCountrySearch($term = '')
    {
        $result = [];
        $term = trim($term);
        if (!empty($term))
        {
            $list = \app\models\Country::find()
                    ->where(['and', ['is_deleted' => false], ['like', 'title', $term]])
                    ->orderBy('title')
                    ->all();
            foreach ($list as $item)
                $result[] = ['label' => $item->title, 'value' => $item->title];
        }
        return Json::encode($result);
    }
    
    public function onAuthSuccess($client)
    {
        $attributes = $client->getUserAttributes();
        if (Yii::$app->user->isGuest)
        {
            $account = \app\models\UserAccount::findOne(['source' => $client->getId(), 'source_id' => $attributes['id']]);
            if (empty($account))
                $result = User::AccountRegister($client, $attributes);
            else
                $result = User::AccountLogin($client, $account);
        }
        else
            $result = User::AccountAssign($client, $attributes);
        switch ($result)
        {
            case User::ACCOUNT_RESULT_REGISTER: // User was registered
                $this->redirect(Url::to(['/profile/index']));
                break;
            case User::ACCOUNT_RESULT_LOGIN: // User was logged
                $this->redirect(Url::to(['/site/index']));
                break;
            case User::ACCOUNT_RESULT_ASSIGN: // Assign new account
                $this->redirect(Url::to(['/profile/index']));
                break;
            case User::ACCOUNT_RESULT_REMOVE: // Unassign SN account
                $this->redirect(Url::to(['/profile/index']));
                break;
            default:
                throw new HttpException(500, $result);
        }
    }
}