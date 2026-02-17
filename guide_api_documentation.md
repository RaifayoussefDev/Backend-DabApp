# Documentation Technique : Module Guides

Ce document détaille les spécifications techniques pour l'implémentation du module "Guides" sur le Panneau Admin et l'Application Mobile.

## 1. Panneau Admin (Backend Integration)

### Objectif : Ajouter un Guide

Pour qu'un administrateur puisse ajouter un guide, le frontend (Admin Panel) doit consommer l'API suivante.

**Endpoint :** `POST /api/admin/guides`
**Authentification :** Requise (Token Bearer, Rôle Admin)

### Format des Données (Payload JSON)

Le formulaire d'ajout doit envoyer les données structurées comme suit. Notez la présence des champs `_ar` pour la version arabe.

```json
{
    "title": "Titre en Anglais/Français",
    "title_ar": "العنوان بالعربية",
    "excerpt": "Court résume (intro) en EN/FR",
    "excerpt_ar": "ملخص قصير بالعربية",
    "content": "<p>Contenu complet (HTML) en EN/FR</p>",
    "content_ar": "<p>المحتوى الكامل (HTML) بالعربية</p>",
    "featured_image": "https://example.com/image.jpg",
    "category_id": 1,
    "author_id": 5,
    "status": "published",
    "is_featured": true,
    "tags": [1, 3],
    "meta_title": "SEO Title EN",
    "meta_title_ar": "عنوان SEO بالعربية",
    "meta_description": "SEO Desc EN",
    "meta_description_ar": "وصف SEO بالعربية",

    // Sections du guide (Optionnel mais recommandé pour les guides riches)
    "sections": [
        {
            "type": "text_image", // Options: text, image, text_image, gallery, video
            "title": "Section Title EN",
            "title_ar": "عنوان القسم بالعربية",
            "description": "<p>Section content EN</p>",
            "description_ar": "<p>محتوى القسم بالعربية</p>",
            "image_url": "https://example.com/sec1.jpg",
            "image_position": "left",
            "order_position": 1
        }
    ]
}
```

### Points Clés pour le Frontend Admin :

1.  **Champs Bilingues** : Prévoir des inputs pour les deux langues (Ex: Onglets "Français" / "Arabe" dans le formulaire).
2.  **Statut** : Le statut peut être `draft` (brouillon), `published` (publié), ou `archived`.
3.  **Sections** : Si le guide est complexe, l'admin peut ajouter des "sections" dynamiques.

---

## 2. Application Mobile (Frontend Integration)

### Objectif : Affichage Multilingue (Arabe & Anglais)

L'API retourne **toujours** les deux versions du contenu dans la même réponse JSON. C'est à l'application mobile de choisir quel champ afficher en fonction de la langue sélectionnée par l'utilisateur.

**Endpoint Récupération :** `GET /api/guides/{slug}` ou `GET /api/guides/id/{id}`

### Structure de la Réponse API

```json
{
    "id": 150,
    "title": "Ultimate Maintenance Guide", // <-- Contenu par défaut (EN/FR)
    "title_ar": "دليل الصيانة الشامل", // <-- Contenu Arabe
    "excerpt": "Summary text...",
    "excerpt_ar": "نص التلخيص...",
    "content": "<p>Full content...</p>",
    "content_ar": "<p>المحتوى الكامل...</p>",
    "sections": [
        {
            "title": "Step 1",
            "title_ar": "الخطوة 1",
            "description": "...",
            "description_ar": "..."
        }
    ]
    // ... autres champs
}
```

### Logique d'Affichage (Pseudo-code)

Le mobile doit vérifier la langue active (`currentLocale`) et sélectionner le champ approprié.

**Exemple de logique (Dart/Flutter ou JS/React Native) :**

```javascript
// Supposons que 'guide' est l'objet reçu de l'API
const isArabic = currentLocale === "ar";

// Composant d'affichage
return (
    <View>
        {/* Titre */}
        <Text style={{ textAlign: isArabic ? "right" : "left" }}>
            {isArabic ? guide.title_ar : guide.title}
        </Text>

        {/* Résumé */}
        <Text>{isArabic ? guide.excerpt_ar : guide.excerpt}</Text>

        {/* Image (Commune aux deux langues) */}
        <Image source={{ uri: guide.featured_image }} />

        {/* Contenu HTML */}
        <HtmlView value={isArabic ? guide.content_ar : guide.content} />
    </View>
);
```

### Règles pour le Mobile :

1.  **Fallback** : Si le champ arabe (`title_ar`) est vide (`null` ou `""`), il est recommandé d'afficher le champ par défaut (`title`) pour éviter d'avoir un écran vide.
2.  **Direction du texte (RTL)** : Si l'utilisateur est en mode Arabe, s'assurer que l'interface et le texte sont alignés à droite (RTL).
