#!/usr/bin/env python
# -*- coding: utf-8 -*-

from pprint import pprint
import ovh
import ovh.exceptions

client = ovh.Client()

# retrieve your account subsidiary to get the right prices
subsidiary = client.get("/me").get("ovhSubsidiary")

# generating a cart
cart = client.post("/order/cart", ovhSubsidiary=subsidiary, _need_auth=False)
client.post("/order/cart/{0}/assign".format(cart.get("cartId")))

# retrieve offers for IP addresses
offers = client.get("/order/cart/{0}/ip".format(cart.get('cartId')))
for item in offers:
    print(item.get("planCode"))

# selected a /24 RIPE IP address
desired_plan_code = "ip-v4-s24-ripe"

# finding the offer in the catalog
desired_offer = None
for item in offers:
    if item.get("planCode") == desired_plan_code:
        desired_offer = item

if desired_offer is None:
    raise Exception("desired offer not found")

pprint(desired_offer)

# adding the offer in the cart
item = client.post("/order/cart/{0}/ip".format(cart.get('cartId')), planCode=desired_offer.get("planCode"), duration="P1M", pricingMode=desired_offer["prices"][0]["pricingMode"], quantity=1)

# retrieving the list of configurations required to order this product
configurations = client.get("/order/cart/{0}/item/{1}/requiredConfiguration".format(cart.get('cartId'), item.get("itemId")))
print("Required configurations:")
pprint(configurations)

# adding the country configuration which is the only one required
client.post("/order/cart/{0}/item/{1}/configuration".format(cart.get('cartId'), item.get("itemId")), label="country", value="FR")

# dry-run quotation
quotation = client.get("/order/cart/{0}/checkout".format(cart.get("cartId")))

# generating the order
order = client.post("/order/cart/{0}/checkout".format(cart.get("cartId")))
print("Order #{} has been generated, available here {}".format(order.get("orderId"), order.get("url")))
