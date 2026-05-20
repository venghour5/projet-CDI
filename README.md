# Projet CDI PHP App

## Base de donnees (nouveau schema)

Le projet utilise maintenant la base `cdi_v2` avec les tables :

- `role`
- `utilisateur`
- `genre`
- `zone`
- `bloc`
- `livre`
- `ressource`
- `reservation`

## Lancer la migration depuis l'ancienne base

La migration copie les donnees de `cdi` vers `cdi_v2` :

```powershell
C:\xampp\php\php.exe scripts\migrate_to_cdi_v2.php
```

## Mettre a jour une base existante (sans reset)

```powershell
C:\xampp\php\php.exe scripts\upgrade_module_supervision_schema.php
```

## Scripts modules ESP32

- Enregistrement initial d'un module:

```powershell
C:\xampp\php\php.exe scripts\register_module.php --ip=10.1.1.4 --name=\"ESP Rayon BD\" --interval=60 --zone=1
```

- Generation du fichier de configuration initiale:

```powershell
C:\xampp\php\php.exe scripts\generate_module_bootstrap.php --module=1 --api-url=http://localhost/cdi_php_app/module_heartbeat.php
```

- Scan supervision (utile en cron):

```powershell
C:\xampp\php\php.exe scripts\run_supervision_scan.php
```

- Verification coherence donnees modules:

```powershell
C:\xampp\php\php.exe scripts\check_modules_consistency.php
```

Avec correction automatique:

```powershell
C:\xampp\php\php.exe scripts\check_modules_consistency.php --fix
```

## Lancer les tests

```powershell
C:\xampp\php\php.exe tests\run_functional_tests.php
```
