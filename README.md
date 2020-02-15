# ConfigurationPage

## Installation

 - placer la classe ConfigurationPage dans le dossier src/
 - Autoloader la classe avec composer
 
Dans le module :
 
```
 <?php
 
 if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
     require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
 }
```

 
## Utilisation

### Dans getContent()
     
```
    public function getContent()
    {
        return \SignalWow\ConfigurationPage\ConfigurationPage::getInstance()
            ->setOptionPrefix('test_')
            ->setOptionTable($this->table)
            ->setOptionIdentifier($this->identifier)
            ->addForm($this->getConfigForm())
            ->processAndRender($this);
    }
```

### Dans install()

Pour initialiser les valeurs par défaut

```
  public function install()
   {
     //...
     $this->configForm->initDefaultConfigurationValues();
     //...
   }
```

