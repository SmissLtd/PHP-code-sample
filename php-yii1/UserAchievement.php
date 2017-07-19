<?php

/**
 * This is the model class for table "user_achievement".
 *
 * The followings are the available columns in table 'user_achievement':
 * @property integer $id
 * @property integer $user_id
 * @property integer $achievement_id
 * @property string $from
 * @property string $till
 * @property string $note
 *
 * The followings are the available model relations:
 * @property Achievement $achievement
 * @property User $user
 */
class UserAchievement extends ActiveRecordExt
{

    public function tableName()
    {
        return 'user_achievement';
    }

    public function rules()
    {
        return array(
            array('user_id, achievement_id, from', 'required'),
            array('user_id, achievement_id', 'numerical', 'integerOnly' => true),
            array('user_id', 'exist', 'className' => 'User', 'attributeName' => 'id'),
            array('user_id', 'uniqueAchievement'),
            array('achievement_id', 'exist', 'className' => 'Achievement', 'attributeName' => 'id'),
            array('from, till', 'prepareDate'),
            array('from, till', 'date', 'format' => 'yyyy-MM-dd'),
            array('note', 'safe'),
        );
    }

    public function prepareDate($attribute, $params)
    {
        if (strtotime($this->$attribute))
            $this->$attribute = date('Y-m-d', strtotime($this->$attribute));
        else
            $this->$attribute = null;
    }
    
    public function uniqueAchievement($attribute, $params)
    {
        $result = true;
        $userAchievement = UserAchievement::model()->find('achievement_id = :a_id AND user_id = :u_id AND '
            . '((t.from > :from AND t.from < :till) OR (t.till > :from AND t.till < :till) OR (t.from < :from AND t.till > :till))', array(':a_id' => $this->achievement_id, ':u_id' => $this->user_id, ':from' => $this->from, ':till' => $this->till));
        if(!empty($userAchievement))
        {
            $result = false;
            $this->addError('user_id', Yii::t('achievements', 'The achievement is already assigned to this user'));
        }
        return $result;
    }

    public function relations()
    {
        return array(
            'achievement' => array(self::BELONGS_TO, 'Achievement', 'achievement_id'),
            'user' => array(self::BELONGS_TO, 'User', 'user_id'),
        );
    }

    public function attributeLabels()
    {
        return array(
            'id' => Yii::t('achievements', 'ID'),
            'user_id' => Yii::t('achievements', 'User'),
            'achievement_id' => Yii::t('achievements', 'Achievement'),
            'from' => Yii::t('achievements', 'From'),
            'till' => Yii::t('achievements', 'Till'),
            'note' => Yii::t('achievements', 'Note'),
        );
    }

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }
}