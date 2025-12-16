# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [1.4.4] - 2025-01-15

### ⚡ Optimisation Performance (Phase 2.1)

- **Cache du tri des routes dynamiques** : Optimisation majeure pour améliorer les performances
  - Ajout d'un cache pour le tri des routes dynamiques (`sortedDynamicRoutesCache`)
  - Le tri n'est effectué qu'une seule fois et réutilisé pour toutes les requêtes suivantes
  - Invalidation automatique du cache lors de l'ajout de nouvelles routes dynamiques
  - Nouvelle méthode privée `invalidateSortedRoutesCache()` pour gérer l'invalidation
  - Modification de `getSortedDynamicRoutes()` pour utiliser le cache

**Bénéfices** :
- Réduction du temps de matching pour les routes dynamiques
- Performance améliorée pour les applications avec beaucoup de routes
- Le tri n'est plus effectué à chaque requête, seulement lors de l'ajout de routes

**Tests** :
- Nouveau fichier `SortedRoutesCacheTest.php` : 6 tests (21 assertions)
  - Test de création du cache lors du premier appel
  - Test de réutilisation du cache lors des appels suivants
  - Test d'invalidation lors de l'ajout d'une nouvelle route
  - Test de régénération après invalidation
  - Test de correction du tri avec le cache
  - Test de performance améliorée

## [1.4.3] - 2025-01-15

### 📚 Documentation Technique (Phase 1.2)

- **Documentation complète** : Création de 4 fichiers de documentation technique
  - `DOCUMENTATION/ARCHITECTURE.md` : Architecture détaillée du routeur
    - Composants principaux (Router, Request, Response, Route Attribute, Middleware)
    - Flux d'exécution complet
    - Optimisations implémentées
    - Groupes de routes
    - Injection de dépendances
    - Gestion des erreurs et sécurité
  - `DOCUMENTATION/ROUTES.md` : Guide complet pour définir des routes
    - Utilisation des attributs PHP 8
    - Routes statiques et dynamiques
    - Paramètres et contraintes
    - Injection de Request et paramètres de route
    - Groupes de routes
    - Génération d'URL
    - Exemples complets (CRUD, contraintes)
  - `DOCUMENTATION/MIDDLEWARES.md` : Guide complet pour les middlewares
    - Interface Middleware
    - Types de middlewares (globaux, groupe, route)
    - Middlewares intégrés (Auth, Role, Permission, CORS, Logging)
    - Création de middlewares personnalisés
    - Injection de dépendances
    - Bonnes pratiques et tests
  - `DOCUMENTATION/PERFORMANCE.md` : Optimisations et performance
    - Optimisations implémentées (séparation statique/dynamique, cache, tri)
    - Performances mesurées
    - Recommandations d'optimisation
    - Optimisations futures (Phase 2.1 : cache du tri)
    - Benchmarks et profiling

## [1.4.2] - 2025-01-15

### 🧪 Tests Supplémentaires (Phase 1.1)

- **Augmentation de la couverture de tests** : Passage de 48 à 73 tests (782 assertions)
  - Nouveau fichier `RouteGroupTest.php` : 9 tests pour les groupes de routes
    - Test du préfixe appliqué aux routes
    - Test des middlewares de groupe
    - Test des middlewares bloquants
    - Test des groupes imbriqués
    - Test de la génération d'URL avec groupes
  - Nouveau fichier `UrlGenerationTest.php` : 13 tests pour la génération d'URL
    - Tests pour routes statiques et dynamiques
    - Tests avec query parameters
    - Tests avec valeurs encodées et caractères spéciaux
    - Tests avec groupes et groupes imbriqués
  - Nouveau fichier `MiddlewareTest.php` : 7 tests pour les middlewares
    - Tests pour middlewares globaux
    - Tests pour middlewares de route
    - Tests d'ordre d'exécution
    - Tests de blocage d'exécution
  - Nouveau fichier `PerformanceTest.php` : 4 tests de performance
    - Test avec beaucoup de routes statiques (100 routes)
    - Test avec beaucoup de routes dynamiques (100 routes)
    - Test de génération d'URL (200 routes, 500 générations)
    - Test de tri des routes dynamiques (50 routes)

### 🔧 Amélioré

- **phpunit.xml** : Suppression des attributs dépréciés pour PHPUnit 11.5
  - Suppression de `beStrictAboutTodoAnnotatedTests`
  - Suppression de `convertDeprecationsToExceptions`
  - Suppression de `verbose`

## [1.4.0] - 2025-11-29

### ✨ Ajouté

- **Tests de sécurité** : Ajout de tests complets pour la protection CRLF injection
  - Tests de sanitization des headers (nom et valeur)
  - Tests de protection contre les caractères de contrôle
  - Tests de validation CORS (origines valides/invalides, wildcard, schémas)
  - Tests de protection DoS (limite de taille du body)
  - Tests de normalisation des noms de headers

- **Tests de middlewares** : Ajout de tests complets pour tous les middlewares intégrés
  - Tests pour `AuthMiddleware` (authentifié/non authentifié)
  - Tests pour `RoleMiddleware` (avec/sans rôle requis)
  - Tests pour `LoggingMiddleware`
  - Tests pour `CorsMiddleware` (preflight, credentials, validation)
  - Tests de chaîne de middlewares

### 🔧 Amélioré

- **Strict Types** : Ajout de `declare(strict_types=1)` dans tous les fichiers source
  - Améliore la type safety et la détection d'erreurs
  - Appliqué à tous les fichiers (Router, Request, Response, Middlewares, etc.)

- **Type Hints** : Amélioration des type hints avec PHP 8
  - Utilisation du type `mixed` pour les paramètres et retours flexibles
  - Amélioration des types pour `getQueryParam()`, `getCookie()`, `getBodyParam()`, `getRouteParam()`
  - Type `?object` pour le container d'injection de dépendances

- **Normalisation des headers** : Les noms de headers sont maintenant normalisés en minuscules
  - Cohérence dans le stockage et la récupération des headers
  - Améliore la compatibilité et la prévisibilité

- **Gestion des erreurs JSON** : Amélioration de la gestion des erreurs d'encodage JSON
  - Utilisation de `JSON_THROW_ON_ERROR` pour une meilleure gestion des exceptions
  - Options JSON optimisées (`JSON_UNESCAPED_UNICODE`, `JSON_UNESCAPED_SLASHES`)
  - Validation stricte du parsing JSON dans Request

- **Validation des URI** : Ajout de validation pour les URI invalides
  - Vérification que `parse_url()` retourne un résultat valide
  - Exception claire en cas d'URI malformée

- **Code Quality** : Refactorisation de la gestion du mode debug
  - Méthode privée `isDebugMode()` pour centraliser la vérification
  - Code plus maintenable et testable

### 🐛 Corrigé

- **Tests** : Correction de tous les tests en échec
  - Correction du test `testResponseHeaders` (normalisation en minuscules)
  - Correction du test `testResponseHeaderSanitization` (vérification complète)
  - Correction du test `testCorsMiddlewareOriginValidation` (utilisation de $_SERVER)
  - Correction du test `testMiddlewareChain` (ajout de l'attribut Route)

### 📊 Statistiques

- **Tests** : 48 tests (31 → 48, +17 nouveaux tests)
- **Assertions** : 103 assertions (60 → 103, +43 nouvelles assertions)
- **Taux de réussite** : 100% (tous les tests passent)
- **Couverture** : Tests de sécurité et middlewares complets

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

