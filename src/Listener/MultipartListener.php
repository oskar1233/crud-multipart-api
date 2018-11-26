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
     * @var string Index of json+api part of request
     */
    const JSON_API_ENTITY_PART_INDEX = 'entity';

    /**
     * @var string Index of file part of request
     */
    const FILE_PART_INDEX = 'file';

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
     * @throws \Cake\Network\Exception\BadRequestException
     */
    public function beforeHandle(Event $event)
    {
        $this->_checkRequestMethods();

        // If we don't have multipart content type, use regular JSON+API handler
        if($this->useParent) {
            return parent::beforeHandle($event);
        }

        // After file processed, save fields to be inserted passed in event
        $this->eventManager()->on('fileProcessed', function(Event $event) {
            // Fields to insert
            if (is_array($event->data)) {
                $this->fieldsToInsert = $event->data;
            }
            
            $this->_validateConfigOptions();
            $this->_checkRequestData();
        });

        // Parse content depending on content type (always multipart, though)
        $contentType = $this->_request()->contentType();

        $parserSelector = new ParserSelector();
        $parser = $parserSelector->getParserForContentType($contentType);

        // Get body parts (elements of multipart)
        $parts = $this->_request()->data();

        // Check if required parts exist
        if(!array_key_exists(self::JSON_API_ENTITY_PART_INDEX, $parts)) {
            throw new BadRequestException('No JSON+API entity found in multipart request (looking for `' . self::JSON_API_ENTITY_PART_INDEX . '` part name).');
        }
        if(!array_key_exists(self::FILE_PART_INDEX, $parts)) {
            throw new BadRequestException('No file entity found in multipart request (looking for `' . self::FILE_PART_INDEX . '` part name).');
        }

        // Get entity (JSON+API) and file parts
        $entity = $parts[self::JSON_API_ENTITY_PART_INDEX];
        $file = $parts[self::FILE_PART_INDEX];

        // Set entity (JSON+API) data for controller
        $this->_controller()->request->data = json_decode($entity, true);

        // Emit fileUploaded event to be handled in controller
        $fileUploadEvent = new Event('fileUploaded', $this, $file);
        $this->_controller()->eventManager()->dispatch($fileUploadEvent);

        return false;
    }

}
