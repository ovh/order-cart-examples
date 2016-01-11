# Configuration associée à votre article de panier

## Principe

Afin de paramétrer plus finement chaque article de votre panier OVH pendant le processus de commande, un système de configuration par article est disponible. Ce système permet ainsi de configurer dans le cas des noms de domaines, pour chaque produit sélectionné: les serveurs DNS associés au domaine, le contact propriétaire, ...

<img alt="configuration-workflow" width="700"
     src="../img/configuration-flow.png" />

## Fonctionnement

Un article de panier est représenté par la route HTTP suivante : `GET /order/cart/{cartId}/item/{itemId}` .
On peut ainsi accéder à toutes les configurations déjà enregistrées pour un article via la route HTTP : `GET /order/cart/{cartId}/item/{itemId}/configuration`.  
Par défaut, aucune configuration n'est présente dans un article de panier.

Une configuration est un ensemble de deux éléments: le libellé de la configuration, ainsi qu'une valeur. Le libellé permet de déterminer le contexte à associer à la valeur de la configuration (par exemple: `DNS` pour une entrée DNS, `OWNER_CONTACT` pour une configuration de contact propriétaire, `ACCEPT_CONDITIONS` pour une configuration de validation de conditions particulières pour un domaine).

Pour connaître la liste des configurations recommandées ou nécessaires pour un article de panier, il est nécessaire de faire un appel à la ressource suivante : `GET /order/cart/{cartId}/item/{itemId}/requiredConfiguration`.  
Cet appel renverra alors une liste des différentes configurations possibles, avec les informations suivantes:

- _label_ : le libellé pour la configuration
- _type_ : le type attendu pour la valeur de la configuration (voir la partie Types de ce guide)
- _fields_ : les potentiels champs nécessaires à fournir pour la configuration (nous y reviendrons dans la partie Types de ce guide également)
- _required_ : indique si la configuration est obligatoire pour acheter l'article

Pour ajouter une configuration, il faut passer par la route `POST /order/cart/{cartId}/item/{itemId}/configuration`, prenant en paramètres :

- dans l'URL:
  - _cartId_ : Identifiant du panier
  - _itemId_ : Identifiant de l'article dans le panier
- dans le corps de la requète, encodée en JSON:
  - _label_ : Libellé de la configuration
  - _value_ : Valeur de la configuration

Une fois la configuration envoyée au serveur, vous recevrez un identifiant correspondant à cette configuration, et cette dernière sera visible via la ressource `GET /order/cart/{cartId}/item/{itemId}/configuration/{configurationId}`.


## Types

Il peut exister différents types pour les configurations : plusieurs types primaires, ainsi que des types étendus.

Les trois types primaires que nous distinguons sont:

- Boolean
- Integer
- String

---
#### Boolean

Représente une valeur VRAI ou FAUSSE.

**Valeurs acceptés pour ce type :**

- "true"
- "false"

---

#### Integer

Représente une valeur numérique non décimale.

**Valeurs acceptés pour ce type :** tous chiffres sans caractères virgule ou point.

---

#### String

Représente une valeur chaîne de caractères.

**Valeurs acceptés pour ce type :** Toutes chaînes de caractères sans distinction.

---

#### Types étendus

Les types "étendus" font référence à des ressources déjà existantes dans l'API. Ils sont composés de plusieurs types simples, et une lecture du schema de cette ressource est requise pour la comprendre.

Par exemple, la configuration d'un contact propriétaire nécessite de fournir les informations de nom, prénom, adresse, etc... cela en devient ainsi un type étendu. Le type requis pour un contact propriétaire sera `/me/contact`, qui correspond à une ressource au sein de l'APIv6. Il conviendra ainsi de créer cette dernière via `POST /me/contact` puis d'ajouter une configuration ayant pour valeur, le chemin d'accès à la ressource créée.

Si on crée un contact propriétaire via `POST /me/contact`, la valeur de retour de ce contact contiendra un `id` et on pourra ainsi récupérer les informations sur ce contact via `GET /me/contact/{id}`. La création d'une configuration `OWNER_CONTACT` aura donc pour valeur associée `/me/contact/{id}`.

Ainsi, tous les types dynamiques sont de la forme `/xyz` indiquant le chemin APIv6 correspondant : ils commencent ainsi tous par le caractère `/` (c'est le moyen de les repérer).

##### Fields

La partie `fields` renvoyée par l'API `GET /order/cart/{cartId}/item/{itemId}/requiredConfiguration` (mentionné précédemment) sert exclusivement pour les types complexes. Elle permet d'indiquer les différentes propriétés qui seront requises lors de la création de la ressource.

Par exemple, pour une configuration de type `/me/contact`, la valeur de fields pourrait être `["firstName", "lastName", "legalForm"]`. Cela signifie que lors de la création du contact, les champs `firstName`, `lastName`, et `legalForm` devront être remplis afin que la configuration soit valide.


## Par l'exemple

Prenons un exemple de code pour bien comprendre le fonctionnement des configurations: nous allons commander un nom de domaine en .FR nécessitant une configuration de contact propriétaire obligatoire.

```py
import ovh
import pprint
client = ovh.Client()

# creation du panier
cart = client.post("/order/cart", ovhSubsidiary="FR")
client.post("/order/cart/{0}/assign".format(cart["cartId"]))

# verification de la disponibilite
domain_name = "amusons-nous-avec-lapi.fr"
domain_result = client.get("/order/cart/{0}/domain".format(cart["cartId"]), domain=domain_name)

if not domain_result or not domain_result[0]["orderable"]:
        raise Exception("Nom de domaine non disponible")

item = client.post("/order/cart/{0}/domain".format(cart["cartId"]), domain=domain_name)

# ici nous voyons qu'une configuration de label OWNER_CONTACT est requise
configurations = client.get("/order/cart/{0}/item/{1}/requiredConfiguration".format(cart["cartId"], item["itemId"]))

for configuration in configurations:
        # configuration contact proprietaire requise
        if configuration["label"] == "OWNER_CONTACT" and configuration["type"] == "/me/contact" and configuration["required"]:
        # creation du contact
                contact = client.post("/me/contact",
                        firstName="Jean",
                        lastName="Dupont",
                        legalForm="individual",
                        address={
                            "country": "FR",
                            "line1": "18 rue de Paris",
                            "city": "Paris",
                            "zip": "01000"},
                        language="fr_FR",
                        email="noreply@ovh.com",
                        phone="+33123456789",
                        birthDay="1980-01-01",
                        birthCountry="FR",
                        birthZip="75000",
                        birthCity="Paris")

                # on ajoute la configuration
                configuration = client.post("/order/cart/{0}/item/{1}/configuration".format(cart["cartId"], item["itemId"]),
                        label="OWNER_CONTACT",
                        value="/me/contact/{0}".format(contact["id"]))

# validation du panier
client.get("/order/cart/{0}/checkout".format(cart["cartId"]))

bon_de_commande = client.post("/order/cart/{0}/checkout".format(cart["cartId"]))
print(u"Order #{0} ({1}) has been generated : {2}"
              .format(bon_de_commande["orderId"],
                      bon_de_commande["prices"]["withTax"]["text"],
                      bon_de_commande["url"]))
```
