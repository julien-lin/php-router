# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [1.3.0] - 2025-11-27

### ✨ Ajouté

- **Validation des paths** : Ajout de la méthode `validatePath()` qui rejette les paths malformés
  - Rejette les doubles slashes (sauf pour la racine)
  - Rejette les trailing slashes (sauf pour la racine)
  - Valide le format des paramètres dynamiques (lettres, chiffres, underscore uniquement)
  
- **Validation des noms de routes** : Vérification d'unicité des noms de routes à l'enregistrement
  - Exception claire en cas de collision
  - Protection pour toutes les méthodes HTTP
  
- **Contraintes de route** : Support des contraintes regex pour les paramètres de route
  - Nouveau paramètre `constraints` dans l'attribut `Route`
  - Validation au niveau de la compilation regex
  - Exemple : `#[Route(path: '/user/{id}', constraints: ['id' => '\d+'])]`
  
- **Optimisation des routes dynamiques** : Tri par spécificité pour améliorer les performances
  - Routes avec plus de paramètres testées en premier
  - Tri secondaire par longueur du path
  - Méthode `getSortedDynamicRoutes()` pour le tri intelligent
  
- **Cache de compilation** : Cache des routes dynamiques compilées
  - Évite la recompilation des mêmes patterns
  - Clé de cache : `path|serialize(constraints)`
  - Améliore les performances lors de l'enregistrement de routes similaires

### 🔧 Amélioré

- **Robustesse** : Validation stricte des paths et noms de routes
- **Performance** : Optimisation de la recherche des routes dynamiques
- **Sécurité** : Validation des paramètres au niveau routeur avec contraintes regex

### 📝 Documentation

- Ajout de la section sur les contraintes de route dans les README (EN/FR)

## [1.2.1] - 2025-11-XX

### 🔧 Amélioré

- Amélioration de la documentation
- Corrections mineures

## [1.2.0] - 2025-11-XX

### ✨ Ajouté

- Support de l'injection de dépendances pour les contrôleurs et middlewares
- Amélioration de l'interface Middleware pour retourner `?Response`

### 🔧 Amélioré

- Documentation bilingue (EN/FR)
- Exemples d'intégration avec autres packages

## [1.1.0] - 2025-11-XX

### ✨ Ajouté

- Support des groupes de routes avec préfixes et middlewares
- Génération d'URL par nom de route
- Index inversé pour recherche O(1) par nom

### 🔧 Amélioré

- Performance de la recherche de routes par nom

## [1.0.0] - 2025-11-XX

### ✨ Ajouté

- Routeur PHP moderne avec support des Attributes PHP 8+
- Routes statiques et dynamiques
- Middlewares (globaux, groupes, routes)
- Gestion des erreurs (404, 405, 500)
- Classes Request et Response
- Middlewares intégrés (Auth, CORS, Logging, Role)
- Support de la génération d'URL

[1.3.0]: https://github.com/julien-lin/php-router/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/julien-lin/php-router/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/julien-lin/php-router/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/julien-lin/php-router/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/julien-lin/php-router/releases/tag/v1.0.0

