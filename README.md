# MultipartJsonApiListener plugin for CakePHP

Basically, the library utilized CakePHP's events mechanism and custom listener for `multipart/form-data` content type.

To handle file and JSON+API payload at once, you'll need to pass two elements in the multipart request:
- file - with name `file`,
- json+api payload - with name `entity`.

More docs soon.

# Example usage

In Controller#initialize method:

```php
$this->loadComponent('Crud.Crud', [
    'actions' => [
        'Crud.Index',
        'Crud.View',
        'Crud.Add',
        'Crud.Edit',
        'Crud.Delete'
    ],
    'listeners' => [
        'MultipartJsonApiListener.Multipart',
        'CrudJsonApi.Pagination',
        'Crud.Search'
    ]
]);
```

In controller:

```php
public function implementedEvents() {
    return [
        'fileUploaded' => 'handleUpload'
    ];
}

public function handleUpload(Event $event) {
    $file = $event->getData();
    $addFields = [
        'url' => 'some'
    ];
    $event->subject->eventManager()->dispatch(new Event('fileProcessed', $this, $addFields));
}
```
