First run `php bin/console doctrine:migrations:diff` to get a fresh migration file. Now search for
`code VARCHAR(255) NOT NULL` within that file. Immediately after that line add the following line:

```
$this->addSql("UPDATE setono_sylius_cms__asset SET code=SUBSTR(REPLACE(path, '/', ''), 1, 32)");
```

This will make sure the newly added `code` field will be unique.
