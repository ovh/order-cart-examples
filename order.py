#!/usr/bin/env python
# -*- coding: utf-8 -*-

import ovh
import ovh.exceptions
try:
    client = ovh.Client()

    # creating a new cart and assign to current user
    cart = client.post("/order/cart", ovhSubsidiary="FR", _need_auth=False)
    client.post("/order/cart/{0}/assign".format(cart.get("cartId")))

    # add domain item to the cart
    while True:
        domain = raw_input("Please enter a domain "
                           "[type Enter if you're done adding domains] : ")
        if not domain:
            break
        try:
            infos = client.get("/order/cart/{0}/domain"
                               .format(cart.get("cartId")), domain=domain)
        except ovh.exceptions.APIError as e:
            print(e)
            continue
        index = 0
        if len(infos) > 1:
            print("The domain {0} has multiples offers, "
                  "please select the one you desire :")
            for index in range(0, len(infos)):
                total_price = None
                for price in infos[index]["prices"]:
                    if price["label"] == "TOTAL":
                        total_price = price["price"]["text"]
                        break
                print(u"[{0}] {1} (phase : {2}) - {3}"
                      .format(index, domain, infos[index]["phase"],
                              total_price))
            index = int(raw_input("Please select your offer: ")) % len(infos)
        offer = infos[index]
        if not offer["orderable"]:
            print("This domain is not available")
            continue

        total_price = None
        for price in offer["prices"]:
            if price["label"] == "TOTAL":
                total_price = price["price"]["text"]
                break
        print(u"Offer selected: {0} (phase : {1}) - {2}"
              .format(domain, infos[index]["phase"], total_price))

        add_to_cart = raw_input("Do you want to add it to your cart ? (Y/N) ")
        if add_to_cart in ("Y", "y", "yes"):
            try:
                client.post("/order/cart/{}/domain"
                            .format(cart.get("cartId")),
                            domain=domain,
                            offerId=offer["offerId"])
            except ovh.exceptions.APIError as e:
                print(e)

    # generate a salesorder
    try:
        salesorder = client.post("/order/cart/{}/checkout"
                                 .format(cart.get("cartId")))
        print(u"Order #{0} ({1}) has been generated : {2}"
              .format(salesorder["orderId"],
                      salesorder["prices"]["withTax"]["text"],
                      salesorder.get("url")))
    except ovh.exceptions.APIError as e:
        print("Unable to generate the order: " + str(e))
except ovh.exceptions.APIError as e:
    print(e)
