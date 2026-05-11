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

## Lancer les tests

```powershell
C:\xampp\php\php.exe tests\run_functional_tests.php
```
