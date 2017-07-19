<?php
namespace Investment\Controller\Admin;

require_once(ROOT . DS . 'vendor' . DS . DS . 'class.uploader.php');

use Platform\Controller\PlatformController;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Uploader;

class ProjectController extends PlatformController
{

    public function create()
    {
        $projectTable = TableRegistry::get('Investment.Project');

        $project = $projectTable->newEntity();

        if ($this->request->is(['post', 'put'])) {
            $projectTable->patchEntity($project, $this->request->data);

            if ($projectTable->save($project)) {
                $this->Flash->success(__('Projekts izveidots'));

                return $this->redirect(['_name' => 'admin_investment_id', 'id' => $project->id, 'lang' => $this->language]);
            }
            $this->Flash->error(__('Neizdevās saglabāt'));
        }

        $this->set('project', $project);

        $this->Breadcrumb->add(__('Izveidot projektu'), 2, Router::url(['_name' => 'investment', 'lang' => $this->language]));
    }

    public function edit($id = 0) {
        $this->useEditable = true;
        $this->useTinymce = true;
        $this->useFiler = true;
        
        $projectTable = TableRegistry::get('Investment.Project');
        $financialTable = TableRegistry::get('Investment.Financial');
        $attachmentTable = TableRegistry::get('Investment.Attachment');

        $project = $projectTable->get($id);
        $financials = $financialTable->find('all', array('conditions' => array('project_id' => $id, 'is_deleted' => 'n')));
        $attachments = $attachmentTable->find('all', array('conditions' => array('project_id' => $id, 'is_deleted' => 'n')));

        if ($this->request->is(['post'])) {
            $post = $this->request->data;
            if ($post['name'] == 'investment_term')
                $post['value'] = date('Y-m-d', strtotime($post['value']));

            $project->{$post['name']} = $post['value'];

            $projectTable->save($project);
        }

        $this->set('id', $id);
        $this->set('project', $project);
        $this->set('financials', $financials);
        $this->set('attachments', $attachments);
        $this->set('attachment_path', dirname(dirname(dirname(dirname(__DIR__)))) . DS . 'Platform' . DS . 'webroot' . DS . 'file' . DS . 'attachments' . DS);

        $this->Breadcrumb->add(__('Projekta labošana'), 2, Router::url(['_name' => 'investment', 'lang' => $this->language]));
    }
    
    public function uploadfinancial() {
        $table = TableRegistry::get('Investment.Financial');
        $uploader = new Uploader();
        $uploadFolder = dirname(dirname(dirname(dirname(__DIR__)))) . DS . 'Platform' . DS . 'webroot' . DS . 'file' . DS . 'attachments' . DS;
        $data = $uploader->upload($_FILES['files'], array(
            'limit' => 10, //Maximum Limit of files. {null, Number}
            'maxSize' => 10, //Maximum Size of files {null, Number(in MB's)}
            'extensions' => null, //Whitelist for file extension. {null, Array(ex: array('jpg', 'png'))}
            'required' => false, //Minimum one file is required for upload {Boolean}
            'uploadDir' => $uploadFolder, //Upload directory {String}
            'title' => array('name'), //New file name {null, String, Array} *please read documentation in README.md
            'removeFiles' => false, //Enable file exclusion {Boolean(extra for jQuery.filer), String($_POST field name containing json data with file names)}
            'replace' => false, //Replace the file if it already exists  {Boolean}
            'perms' => null, //Uploaded file permisions {null, Number}
            'onCheck' => null, //A callback function name to be called by checking a file for errors (must return an array) | ($file) | Callback
            'onError' => null, //A callback function name to be called if an error occured (must return an array) | ($errors, $file) | Callback
            'onSuccess' => null, //A callback function name to be called if all files were successfully uploaded | ($files, $metas) | Callback
            'onUpload' => null, //A callback function name to be called if all files were successfully uploaded (must return an array) | ($file) | Callback
            'onComplete' => null, //A callback function name to be called when upload is complete | ($file) | Callback
            'onRemove' => null //A callback function name to be called by removing files (must return an array) | ($removed_files) | Callback
        ));

        if($data['isComplete']){
            $file = $data['data']['metas'][0];
            $entityData = array(
                'project_id' => $this->request->param('id'),
                'name' => $file['old_name'],
                'file' => $file['name'],
                'size' => $file['size'],
                'type' => implode('/', $file['type'])
            );
            if ($entityData['type'] == 'application/pdf')
            {
                $imagick = new \Imagick();
                $imagick->setResolution(200, 200);
                $imagick->readImage($uploadFolder . $entityData['file'] . '[0]');
                $imagick->writeimage($uploadFolder . pathinfo($uploadFolder . $entityData['file'], PATHINFO_FILENAME) . ".png");
                $imagick->destroy();
                $entityData['preview'] = pathinfo($uploadFolder . $entityData['file'], PATHINFO_FILENAME) . ".png";
            }
            $entity = $table->newEntity();
            $table->patchEntity($entity, $entityData);
            $table->save($entity);
            echo json_encode($file['old_name']);
        }

        if($data['hasErrors']){
            $errors = $data['errors'];
            echo json_encode($errors);
        }
        exit;
    }
    
    public function deletefinancial() {
        $table = TableRegistry::get('Investment.Financial');
        $table->updateAll(array('is_deleted' => 'y'), array('name' => $this->request->data('file'), 'project_id' => $this->request->data('id')));
        exit;
    }

    public function uploadattachment() {
        $table = TableRegistry::get('Investment.Attachment');
        $uploader = new Uploader();
        $data = $uploader->upload($_FILES['files'], array(
            'limit' => 10, //Maximum Limit of files. {null, Number}
            'maxSize' => 10, //Maximum Size of files {null, Number(in MB's)}
            'extensions' => null, //Whitelist for file extension. {null, Array(ex: array('jpg', 'png'))}
            'required' => false, //Minimum one file is required for upload {Boolean}
            'uploadDir' => dirname(dirname(dirname(dirname(__DIR__)))) . DS . 'Platform' . DS . 'webroot' . DS . 'file' . DS . 'attachments' . DS, //Upload directory {String}
            'title' => array('name'), //New file name {null, String, Array} *please read documentation in README.md
            'removeFiles' => false, //Enable file exclusion {Boolean(extra for jQuery.filer), String($_POST field name containing json data with file names)}
            'replace' => false, //Replace the file if it already exists  {Boolean}
            'perms' => null, //Uploaded file permisions {null, Number}
            'onCheck' => null, //A callback function name to be called by checking a file for errors (must return an array) | ($file) | Callback
            'onError' => null, //A callback function name to be called if an error occured (must return an array) | ($errors, $file) | Callback
            'onSuccess' => null, //A callback function name to be called if all files were successfully uploaded | ($files, $metas) | Callback
            'onUpload' => null, //A callback function name to be called if all files were successfully uploaded (must return an array) | ($file) | Callback
            'onComplete' => null, //A callback function name to be called when upload is complete | ($file) | Callback
            'onRemove' => null //A callback function name to be called by removing files (must return an array) | ($removed_files) | Callback
        ));

        if($data['isComplete']){
            $file = $data['data']['metas'][0];
            $entity = $table->newEntity();
            $table->patchEntity($entity, array(
                'project_id' => $this->request->param('id'),
                'name' => $file['old_name'],
                'file' => $file['name'],
                'size' => $file['size'],
                'type' => implode('/', $file['type'])
            ));
            $table->save($entity);
            echo json_encode($file['old_name']);
        }

        if($data['hasErrors']){
            $errors = $data['errors'];
            echo json_encode($errors);
        }
        exit;
    }
    
    public function deleteattachment() {
        $table = TableRegistry::get('Investment.Attachment');
        $table->updateAll(array('is_deleted' => 'y'), array('name' => $this->request->data('file'), 'project_id' => $this->request->data('id')));
        exit;
    }
    
    public function uploadphoto() {
        $projectTable = TableRegistry::get('Investment.Project');
        $project = $projectTable->get($this->request->param('id'));
        $uploader = new Uploader();
        $data = $uploader->upload($_FILES['files'], array(
            'limit' => 1, //Maximum Limit of files. {null, Number}
            'maxSize' => 10, //Maximum Size of files {null, Number(in MB's)}
            'extensions' => array('jpg', 'png'), //Whitelist for file extension. {null, Array(ex: array('jpg', 'png'))}
            'required' => true, //Minimum one file is required for upload {Boolean}
            'uploadDir' => dirname(dirname(dirname(dirname(__DIR__)))) . DS . 'Platform' . DS . 'webroot' . DS . 'file' . DS . 'attachments' . DS, //Upload directory {String}
            'title' => array('name'), //New file name {null, String, Array} *please read documentation in README.md
            'removeFiles' => false, //Enable file exclusion {Boolean(extra for jQuery.filer), String($_POST field name containing json data with file names)}
            'replace' => false, //Replace the file if it already exists  {Boolean}
        ));

        if($data['isComplete']){
            $file = $data['data']['metas'][0];
            $project->photo_name = $file['old_name'];
            $project->photo_file = $file['name'];
            $project->photo_size = $file['size'];
            $project->photo_type = implode('/', $file['type']);
            $projectTable->save($project);
            echo json_encode($file['old_name']);
        }

        if($data['hasErrors']){
            $errors = $data['errors'];
            echo json_encode($errors);
        }
        exit;
    }
    
    public function uploadthumb() {
        $projectTable = TableRegistry::get('Investment.Project');
        $project = $projectTable->get($this->request->param('id'));
        $uploader = new Uploader();
        $data = $uploader->upload($_FILES['files'], array(
            'limit' => 1, //Maximum Limit of files. {null, Number}
            'maxSize' => 10, //Maximum Size of files {null, Number(in MB's)}
            'extensions' => array('jpg', 'png'), //Whitelist for file extension. {null, Array(ex: array('jpg', 'png'))}
            'required' => true, //Minimum one file is required for upload {Boolean}
            'uploadDir' => dirname(dirname(dirname(dirname(__DIR__)))) . DS . 'Platform' . DS . 'webroot' . DS . 'file' . DS . 'attachments' . DS, //Upload directory {String}
            'title' => array('name'), //New file name {null, String, Array} *please read documentation in README.md
            'removeFiles' => false, //Enable file exclusion {Boolean(extra for jQuery.filer), String($_POST field name containing json data with file names)}
            'replace' => false, //Replace the file if it already exists  {Boolean}
        ));

        if($data['isComplete']){
            $file = $data['data']['metas'][0];
            $project->thumb_name = $file['old_name'];
            $project->thumb_file = $file['name'];
            $project->thumb_size = $file['size'];
            $project->thumb_type = implode('/', $file['type']);
            $projectTable->save($project);
            echo json_encode($file['old_name']);
        }

        if($data['hasErrors']){
            $errors = $data['errors'];
            echo json_encode($errors);
        }

        exit;
    }

    public function delete($id = 0) {
        $projectTable = TableRegistry::get('Investment.Project');
        $projectTable->markDeleted($id);

        return $this->redirect($this->referer());
    }
    
    public function switchvisibility()
    {
        $projectTable = TableRegistry::get('Investment.Project');
        $project = $projectTable->get($this->request->param('id'));
        $project->visible = !$project->visible;
        $projectTable->save($project);
        if ($project->visible)
            echo json_encode(array('text' => __('Mark invisible'), 'class' => 'green'));
        else
            echo json_encode(array('text' => __('Mark visible'), 'class' => 'blue'));
        exit;
    }
}
