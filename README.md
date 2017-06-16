# php-cronManager
php 定时任务管理器

# 配置
```php
//Conf/config.yml
# phpCronManager config
manager:
  # Work dir
  dir: ''

# phpCronManager task config
task:
  # Task uuid
  sendEmail:
    # Task script path
    script: ''
    # Runtime args for task script
    args: {tenantId: 1}
    # Enable error log for task
    # Values: On/Off
    # Default: On
    error_log: On
    # Error log file
    log_file: ''
```
# 安装
```php
{
    "require": {
        "php-cronManager/php-cronManager": "~1.0"
    }
}
```

# 使用
```php
phpCronManager start/stop/restart/ps/kill [task-uuid]
```
