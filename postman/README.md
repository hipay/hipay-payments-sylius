# Collection Postman – Webhook HiPay

Cette collection permet de tester le webhook HiPay du plugin Sylius (`POST /payment/hipay/notify`).

## Import

1. Ouvrir Postman.
2. **Import** → choisir le fichier `HiPay-Webhook.postman_collection.json`.

## Variables de collection

À définir dans la collection (onglet **Variables**) ou dans un environnement :

| Variable          | Description                                                                 | Exemple                                                                  |
|-------------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------------|
| `base_url`        | URL de base de l’application                                                | `http://localhost:8000` ou `https://hipay-plugin.wip`                    |
| `webhook_secret`   | Secret passphrase du compte HiPay (environnement test ou production selon votre config) | La même valeur que dans Sylius pour le compte utilisé par la transaction |

**Important :** Le serveur récupère le secret via la `transaction_reference` du body (compte associé au paiement). Pour que la signature soit valide, `webhook_secret` doit correspondre au secret du compte lié à cette transaction dans Sylius.

## Requêtes

- **Notification - Payment Captured (118)** – Paiement capturé
- **Notification - Payment Authorized (116)** – Paiement autorisé
- **Notification - Refused (113)** – Paiement refusé
- **Notification - Refunded (125)** – Remboursé
- **Notification - Cancelled (115)** – Annulé
- **Reject - Invalid signature** – Test rejet 403 (signature invalide)
- **Reject - Missing signature** – Test rejet 403 (signature absente)

Chaque requête de notification utilise un **Pre-request Script** qui calcule le header `X-Allopass-Signature` :

- Algorithme : **SHA256**
- Valeur : `SHA256(corps_brut_requête + webhook_secret)` en **hexadécimal**
- Le corps brut est exactement celui envoyé (Content-Type: `application/x-www-form-urlencoded`)

Si le script ne peut pas calculer la signature (ex. module non disponible), la valeur du header reste un placeholder : dans ce cas, calculez la signature à la main et mettez-la dans le header `X-Allopass-Signature`.

## Calcul manuel de la signature (optionnel)

Sous Linux/macOS :

```bash
BODY='state=completed&attempt_id=1&transaction_reference=800417743755&status=118'
SECRET='votre_secret'
echo -n "${BODY}${SECRET}" | sha256sum | cut -d' ' -f1
```

Utilisez exactement le même `body` que dans la requête (ordre des paramètres identique).

## Test avec une vraie transaction

Pour que le webhook traite la notification (mise à jour du paiement Sylius, création d’un `PaymentRequest`, etc.) :

1. Utilisez une `transaction_reference` qui existe dans votre base (table `hipay_transaction` / champ lié au paiement).
2. Adaptez `order[id]` si votre traitement en dépend.
3. Vérifiez que le compte HiPay associé à ce paiement a bien le même `webhook_secret` que la variable de la collection.

## Réponses attendues

- **200** : Webhook accepté (signature valide, message éventuellement mis en file pour traitement asynchrone selon votre config).
- **403** : Signature invalide ou absente.
- **400** : Payload invalide (ex. body vide ou mal formé).
