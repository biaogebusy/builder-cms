### 备份数据库

### 卸载非D10模块
进入扩展管理或使用命令卸载
```
#卸载命令
drush pmu autogrow swiftmailer ckeditor_uploadimage field_name_prefix_remove ckeditor_font -y
```

### 卸载非必要主题
进入外观管理或使用命令卸载
```
drush theme:uninstall seven
drush theme:uninstall bartik
drush theme:uninstall classy
drush theme:uninstall stable
```

### 切换10.x分支

### 执行更新

访问/update.php或使用命令

```
drush updb
```
