<?php
namespace MultipartJsonApiListener\Listener;

use CrudJsonApi\Listener\JsonApiListener;
use Crud\Listener\BaseListener;
use Cake\Core\Configure;
use Cake\Network\Exception\BadRequestException;
use Crud\Error\Exception\CrudException;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventManager;

use h4cc\Multipart\ParserSelector;

class MultipartListener extends JsonApiListener
{
    use EventDispatcherTrait;

    /**
     * @var [] Additional fields to insert to entity after file uploads.
     */
    protected $fieldsToInsert = [];

    /**
     * @var boolean Should listener fall back to JSON API.
     */
    protected $useParent = false;

    /**
     * Override JsonApiListener method to NOT enforce JSON API request methods.
     * Instead, enforce multipart content type.
     *
     * @throws \Cake\Network\Exception\BadRequestException
     * @return bool
     */
    protected function _checkRequestMethods()
    {
        if ($this->_request()->is('put')) {
            throw new BadRequestException('JSON API does not support the PUT method, use PATCH instead');
        }

        if (!$this->_request()->contentType()) {
            return true;
        }

        if (!preg_match('/^multipart\/form-data/', $this->_request()->contentType())) {
            try {
                parent::_checkRequestMethods();
                $this->useParent = true;
                return true;
            } catch(BadRequestException $e) {
                throw new BadRequestException("Multipart requests require the \"$multipartContentType\" Content-Type header");
            }
        }

        return true;
    }

    public function implementedEvents() {
        $parentEvents = parent::implementedEvents();
        $selfEvents = ['Crud.beforeSave' => 'beforeSave'];

        return is_array($parentEvents)
            ? array_merge($parentEvents, $selfEvents)
            : $selfEvents;
    }

    /**
     * Save additional fields to entity before saving.
     */
    public function beforeSave(Event $event) {
        if($this->useParent) return true;

        $entity = $event->getSubject()->entity;

        foreach($this->fieldsToInsert as $key => $value) {
            $entity->set($key, $value);
        }
    }

    /**
     * Override JsonApi's beforeHandle to extract file's data from request data.
     */
    public function beforeHandle(Event $event)
    {
        $this->_checkRequestMethods();

        if($this->useParent) {
            return parent::beforeHandle($event);
        }

        $this->eventManager()->on('fileProcessed', function(Event $event) {
            // Fields to insert
            if (is_array($event->data)) {
                $this->fieldsToInsert = $event->data;
            }
            
            $this->_validateConfigOptions();
            $this->_checkRequestData();
        });

        $contentType = $this->_request()->contentType();

        $parserSelector = new ParserSelector();
        $parser = $parserSelector->getParserForContentType($contentType);
        $parts = $parser->parse($this->_request()->input());

        $bodies = array_reduce($parts, function($parts, $part) {
            $contentDisp = $part['headers']['content-disposition'][0];

            // Extract part's name from content-disposition
            $matches = [];
            preg_match('/^.*\sname="(.*?)".*$/', $contentDisp, $matches);
            $name = $matches[1];

            $parts[$name] = $part['body'];
            return $parts;
        });

        var_dump($bodies);

        // and emptiness conds

        $entity = $bodies['entity'];
        $file = $bodies['file'];
        
        $this->_controller()->request->data = json_decode($entity, true);

        $fileUploadEvent = new Event('fileUploaded', $this, $file);
        $this->_controller()->eventManager()->dispatch($fileUploadEvent);

        return false;
    }

}
