# MultipartJsonApiListener plugin for CakePHP

Docs soon.

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
