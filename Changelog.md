# Change Log

## [3.0.10] - 2017-08-31
### Corrections
- CartLocker : correction sur champ 'id_transaction' non requis

## [3.0.9] - 2017-07-26
### Modifications
- Moyens de paiement Front Office : suppression filtrage compatibilité Type de débit / Moyen de paiement

## [3.0.8] - 2017-07-19
### Ajouts
- Gestion paiements fractionnés (ANCV + paramètre Z): champ de configuration pour chaque moyen de paiement, traitement IPN spécifique, gestion paramètre Z
- Compatibilité PrestaShop 1.7 : gestion de la nouvelle fonctionnalité "Recalculer les frais de port après modification de commande"
- Capture manuelle : validation du montant saisi avec gestion de la décimale de la devise (ajout sur Remboursement manuel)
- Support basique de surcharges : classe principale du module dans 'override/modules/etransactions' et classes utilitaires dans 'override/modules/etransactions/classes'

### Modifications
- Page de redirection vers le paiement : style
- Page d'attente de retour de paiement : style, augmentation des itérations (10 => 20)
- CartLocker : ajout 'id_transaction' pour gestion paiements fractionnés
- Automatisation Back-Office : désactivée par défaut, alerte dans la configuration du module
- Documentation : suppression du PDF dans le module, récupération lors de l'appel au service de vérification de version E-Transactions
- Traductions

### Corrections
- Capture après autorisation (paiement à l'expédition...) : correction problème de mise à jour de montant (intval)

## [3.0.7] - 2017-06-27
### Ajouts
- Compatibilité PrestaShop 1.7.1.2 : messages Back Office (changement d'appel Message::getMessagesByOrderId => CustomerThread::getCustomerMessagesOrder)
- Remboursement manuel : validation du montant saisi

### Modifications
- Configuration : fonctionnement du paramétrage 3-D Secure

### Corrections
- PrestaShop 1.6 / 1.7 : double appel au remboursement à partir de la version 1.6.1.1
- Statut de paiement "Payé partiellement" : considéré comme "paid" pour permettre le remboursement standard
- Remboursements BO pour les paiements 3x

## [3.0.6] - 2017-04-04
### Modifications
- Compatibilité PrestaShop 1.7 : amélioration de l'affichage des moyens de paiement, aide du module, suppression des unregisterHook manuels
- Validation cohérence des montants pour les remboursements manuels
- Installation : renforcement contrôle existence table ETRANS_order
- Installation / désinstallation : écriture .htaccess pour redirection si ancien module installé


## [3.0.5] - 2017-01-23
### Ajouts
- Compatibilité PrestaShop 1.7
- Moyens de paiement MasterPass, Illicado, ANCV

### Modifications
- Installation : vérification présence colonnes 'method' et 'carte_num' dans la table des commandes du module

## [3.0.4] - 2016-09-30
### Ajouts
- Moyens de paiement : choix du mode d'affichage (module, moyen de paiement, module + moyen de paiement) dans le Back et Front Office
- Activation / Désactivation de l'automatisation des actions Back Office sur les commandes (remboursement automatique...)

### Modifications
- Validation de commande : amélioration de la gestion des erreurs
- Traductions
- CartLocker : suppression à la désinstallation du module
- Configuration : gestion des variables à conserver lors de la désinstallation / réinstallation
- Paiement à l'expédition : affichage bouton capture totale sur la commande uniquement si capture manuelle configurée dans le module

### Corrections
- PrestaShop 1.5 : prise en compte d'un bug PrestaShop de Context bloquant la création de commande pour les versions inférieures à 1.5.5
- Paiement en 3 fois : suppression de la demande remboursement lors de l'annulation des futures échéance
- Ajout de carte dans la configuration du module : non prise en compte du paiement en 3 fois, erreurs à l'ajout

## [3.0.3] - 2016-09-01
### Ajouts
- Installation : enrichissement des messages d'erreur dans le Back Office et alimentation des logs du module
- CartLocker : mécanisme de contrôle pour éventuels cas où plusieurs appels IPN sont traités par PrestaShop pour un même panier

### Modifications
- Installation : ordre des étapes

## [3.0.2] - 2016-07-21
### Ajouts
- Remboursement standard avec bon de réduction : gestion de la saisie d'un montant manuel et adaptation si le remboursement inclut un code promotionnel
- Script d'upgrade à partir de la version 1.9.0 pour création de la colonne '3DS' des cartes et alimentation des valeurs
- Script d'upgrade pour alimentation de la colonne 'initial_amount' si non renseigné pour des commandes utilisant le module dans des versions antérieures

## [3.0.1] - 2016-07-12
### Modifications
- Traductions
- Chemin de template pour le retour boutique suite paiement
- OnePageCheckout : inclusion du CSS dans le template pour prise en compte dans le chargement Ajax

### Corrections
- Paiement en 3 fois : correction de la date de la 3e échéance lors de la réception de l'appel IPN du 2e prélèvement
