<?php

namespace deka6pb\autoparser\models;

use deka6pb\autoparser\components\Abstraction\IDataTimeFormats;
use deka6pb\autoparser\components\Abstraction\IItemStatus;
use deka6pb\autoparser\components\Abstraction\IItemType;
use deka6pb\autoparser\components\DateTimeStampBehavior;
use deka6pb\autoparser\components\TItemStatus;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\BaseActiveRecord;

/**
 * This is the model class for table "posts".
 *
 * @property integer $id
 * @property integer $type
 * @property string $text
 * @property integer $status
 * @property string $tags
 * @property integer $sid
 * @property string $provider
 * @property string $created
 * @property string $published
 * @property string $url
 *
 * @property PostFile[] $postFiles
 * @property Files[] $files
 * @property Files $postFile
 */
class Posts extends \yii\db\ActiveRecord implements IItemStatus, IItemType, IDataTimeFormats
{
    use TItemStatus;

   // public $files;

    const SCENARIO_INSERT = 'create';
    const SCENARIO_UPDATE = 'update';

    public function init() {

    }
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'posts';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'status', 'sid', 'provider'], 'required'],
            [['type', 'status', 'sid'], 'integer'],
            ['sid', 'unique'],
            [['text'], 'string'],
            [['tags', 'provider'], 'string', 'max' => 256],
            [['url'], 'string', 'max' => 2083]
        ];
    }

    protected function addCondition($query, $attribute, $partialMatch = false)
    {
        if (($pos = strrpos($attribute, '.')) !== false) {
            $modelAttribute = substr($attribute, $pos + 1);
        } else {
            $modelAttribute = $attribute;
        }

        $value = $this->$modelAttribute;
        if (trim($value) === '') {
            return;
        }

        /*
         * The following line is additionally added for right aliasing
         * of columns so filtering happen correctly in the self join
         */
        $attribute = $this->tableName().".".$attribute;

        if ($partialMatch) {
            $query->andWhere(['like', $attribute, $value]);
        } else {
            $query->andWhere([$attribute => $value]);
        }
    }

    public function behaviors()
    {
        return [
            'DateTimeStampBehavior' => [
                'class' => DateTimeStampBehavior::className(),
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => 'created',
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => 'published',
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'text' => 'Text',
            'status' => 'Status',
            'tags' => 'Tags',
            'post_file_id' => 'Post File ID',
            'sid' => 'Sid',
            'provider' => 'Provider',
            'created' => 'Created',
            'published' => 'Published',
            'url' => 'Url'
        ];
    }

    public static function getStatusAliases(){
        return [
            ''                              => 'All',
            self::STATUS_NEW                => 'New',
            self::STATUS_PUBLISHED          => 'Published',
            self::STATUS_STOPPED            => 'Stoped',
        ];
    }

    public static function getTypesAliases(){
        return [
            self::TYPE_TEXT                 => 'txt',
            self::TYPE_IMG                  => 'img',
            self::TYPE_GIF                  => 'gif',
        ];
    }

    //region Transaction
    public function transactions()
    {
        return [
            self::SCENARIO_INSERT => self::OP_INSERT,
            //self::SCENARIO_UPDATE => self::OP_UPDATE,
        ];
    }

    public function stopTransaction() {
        $transaction = self::getDb()->getTransaction();
        if($transaction)
            $transaction->rollback();
    }
    //endregion

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_INSERT => ['type', 'text', 'status', 'tags', 'file_id', 'sid', 'provider', 'created', 'published', 'url'],
            self::SCENARIO_UPDATE => ['published'],
        ];
    }

    //region Relations
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPostFiles()
    {
        return $this->hasMany(PostFile::className(), ['post_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiles()
    {
        return $this->hasMany(Files::className(), ['id' => 'file_id'])->viaTable('post_file', ['post_id' => 'id']);
    }
    //endregion

    /**
     * @return string Читабельный статус поста.
     */
    public function getStatus()
    {
        $status = self::getStatusAliases();
        return $status[$this->status];
    }

    public function getNewPosts($count) {
        return $this::find()
            ->with('files')
            ->where(['status' => self::STATUS_NEW])
            ->limit($count)
            ->all();
    }

    //region Get and Set
    public function setText($text) {
        $text = addslashes(strip_tags(str_replace(array("<br>", "<br/>", "<br />"), "", $text)));
        $this->text = $text;
    }

    public function getText() {
        return $this->text;
    }
    //endregion

    public function beforeSave($insert) {
        if(!empty($this->files)) {
            foreach($this->files AS $file) {
                if(!$file->validate())
                    return false;

                if(!$file->save()) {
                    $file->stopTransaction();
                    return false;
                }
            }
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes) {
        if(!empty($this->files)) {
            foreach($this->files AS $file) {
                if(!$file->validate())
                    return false;

                if(!$file->save()) {
                    $file->stopTransaction();
                    return false;
                }

                $postFile = new PostFile();
                $postFile->post_id = $this->id;
                $postFile->file_id = $file->id;

                if(!$postFile->validate() || !$postFile->save()) {
                    $postFile->stopTransaction();
                    return false;
                }
            }
        }

        return parent::afterSave($insert, $changedAttributes);
    }

    public static function countNewPosts() {
        return Posts::find()
            ->where(['status' => self::STATUS_NEW])
            ->count();
    }
}
