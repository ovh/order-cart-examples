#!/usr/bin/env python
# -*- coding: utf-8 -*-

# Search for a given domain on featured TLDs

import ovh, ovh.exceptions
client = ovh.Client()

try:
    # retrieve all extension that you can order
    extensions = client.get("/domain/data/extension", country="FR")

    # create a new cart
    cart = client.post("/order/cart", ovhSubsidiary="FR")
    domain = raw_input("Please enter a domain-name (without the dot and extension) : ")

    # get availability for featured extensions
    for extension in extensions[0:10]:
        domain_ext = domain + "." + extension
        try:
            result = client.get("/order/cart/{}/domain".format(cart.get("cartId")), domain=domain_ext)
            if result[0].get("orderable"):
                print(domain_ext + " is available")
            else:
                print(domain_ext + " is NOT available")
        except ovh.exceptions.APIError as e:
            print(e)
except ovh.exceptions.APIError as e:
    print(e)
