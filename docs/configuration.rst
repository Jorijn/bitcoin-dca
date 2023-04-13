.. _configuration:

Configuration
=============

Bitcoin DCA uses `environment variables <https://en.wikipedia.org/wiki/Environment_variable>`_ to configure the inner workings of the tool. An environment variable looks like this: ``SOME_CONFIGURATION_KEY=valuehere``.

Getting Started Template
------------------------

.. literalinclude:: ../.env.dist
   :language: bash
   :linenos:

Available Configuration
-----------------------

This part of the documentation is split up in generic application settings that decide how the tool should act for Dollar Cost Averaging. The last part is for exchange specific configuration like API keys.

Application Settings
^^^^^^^^^^^^^^^^^^^^

WITHDRAW_ADDRESS
""""""""""""""""
You can either use this or ``WITHDRAW_XPUB``. Choosing this one will make the tool withdraw to the same Bitcoin address every time.

**Example**: ``WITHDRAW_ADDRESS=3AT3tf4cVfGRaQ87HpGQppTYmMrb5kpGQb``

WITHDRAW_XPUB
"""""""""""""
You can either use this or ``WITHDRAW_ADDRESS``. Choosing this one will make the tool withdraw to a new receiving address every time a withdrawal is being made by the tool. It'll start at the first address at index 0, so make sure to generate a new account or key when using this method.

**Example**: ``WITHDRAW_XPUB=ypub6Y4RxNmNrdnwdwxERYnXa9rGd4upqeeJ3ixkJQUCQL8UcwYtXj86eXS5fVGU5xsmuuwRp3pKcdci89yiCmA9t2Mhi8cyEDD5P6w2NbfmWqT``

EXCHANGE
""""""""
This configuration value determines which exchange will be used for buys and withdrawals. The default value is BL3P.

Available options: ``bl3p``, ``bitvavo``, ``kraken``, ``binance``

**Example**: ``EXCHANGE=bl3p``

DISABLE_VERSION_CHECK
"""""""""""""""""""""
Bitcoin DCA will contact GitHub every time it is executed to let you know if there is a newer version available.
Newer versions bring important security updates and new features. It transmits no information about your local environment.
You can audit `the code here <https://github.com/Jorijn/bitcoin-dca/blob/master/src/EventListener/CheckForUpdatesListener.php>`_.
You can completely disable remote version checking by uncommenting this setting:

**Example**: DISABLE_VERSION_CHECK=1

Email & Telegram notifications
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
To provide in-depth information about sending notifications through email and telegram, :ref:`please see this article <getting-notified>`.

Exchange: BL3P
^^^^^^^^^^^^^^

BL3P_PUBLIC_KEY
"""""""""""""""
This is the identifying part of the API key that you created on the BL3P exchange. You can find it there under the name **Identifier Key**.

**Example**: ``BL3P_PUBLIC_KEY=0a12345b-01a1-1a1a-012a-a1bc23ef45bg``

BL3P_PRIVATE_KEY
""""""""""""""""
This is the private part of your API connection to BL3P. It's an encoded secret granting access to your BL3P account.

**Example**: ``BL3P_PRIVATE_KEY=aHR0cHM6Ly93d3cueW91dHViZS5jb20vd2F0Y2g/dj1kUXc0dzlXZ1hjUQ==``

BL3P_FEE_PRIORITY
"""""""""""""""""
This will send the withdrawal as low, medium or high priority. The default is low. Low priority is the cheapest but
will take the longest to confirm. High priority is the most expensive but will be confirmed the fastest.

**Example**: ``BL3P_FEE_PRIORITY=medium``

BL3P_API_URL (optional)
"""""""""""""""""""""""
The endpoint where the tool should connect to.

**Example**: ``BL3P_API_URL=https://api.bl3p.eu/1/``

Exchange: Bitvavo
^^^^^^^^^^^^^^^^^

BITVAVO_API_KEY
"""""""""""""""
This is the identifying part of the API key that you created on the Bitvavo exchange.

**Example**: ``BITVAVO_API_KEY=1006e89gd84e8f3a5209b2762d1bbef36eds5e6108e7696f6117556830b0e3dy``

BITVAVO_API_SECRET
""""""""""""""""""
This is the private part of your API connection to Bitvavo.

**Example**: ``BITVAVO_API_SECRET=aHR0cHM6Ly93d3cueW91dHViZS5jb20vd2F0Y2g/dj1kUXc0dzlXZ1hjUQ==``

BITVAVO_API_URL (optional)
""""""""""""""""""""""""""
The endpoint where the tool should connect to.

**Example**: ``BITVAVO_API_URL=https://api.bitvavo.com/v2/``

Exchange: Kraken
^^^^^^^^^^^^^^^^

KRAKEN_API_KEY
""""""""""""""
This is the identifying part of the API key that you created on the Kraken exchange.

**Example**: ``KRAKEN_API_KEY=1006e89gd84e8f3a5209b2762d1bbef36eds5e6108e7696f6117556830b0e3dy``

KRAKEN_PRIVATE_KEY
""""""""""""""""""
This is the private part of your API connection to Kraken. It’s an encoded secret granting access to your Kraken account.

**Example**: ``KRAKEN_PRIVATE_KEY=aHR0cHM6Ly93d3cueW91dHViZS5jb20vd2F0Y2g/dj1kUXc0dzlXZ1hjUQ==``

KRAKEN_WITHDRAW_DESCRIPTION
"""""""""""""""""""""""""""
Kraken secured the platform by limiting API usage to pre-whitelisted withdrawal addresses. This makes it a lot more secure but unfortunately limits the tool to one withdrawal address thus disabling XPUB generation. On Kraken, go to Funding and create a new Bitcoin withdrawal address and for description use something without special symbols or spaces. Configure the value here.

**Example**: ``KRAKEN_WITHDRAW_DESCRIPTION=bitcoin-dca``

KRAKEN_API_URL (optional)
"""""""""""""""""""""""""
The endpoint where the tool should connect to.

**Default**: ``KRAKEN_API_URL=https://api.kraken.com/``

KRAKEN_FEE_STRATEGY (optional)
""""""""""""""""""""""""""""""
When you request to buy 100 EUR/USD from Kraken they assume you want to buy a minimum of 100 by default. If the fee would be 0.30 that would be added to the 100, resulting in 100.30 being deducted from your EUR/USD balance. If you're transferring a fixed amount of money for a fixed amount of DCA cycles this would result in a lack of balance for the final complete DCA purchase of that cycle.

Option ``include`` (default): deducts the fee estimation from your order, this will ensure you have enough balance left for the final DCA cycle.
Option ``exclude``: Kraken default, the tool will order for 100 and Kraken will pay the fee with the remainder of your balance.

**Default**: ``KRAKEN_FEE_STRATEGY=include``

KRAKEN_TRADING_AGREEMENT (only for German residents)
""""""""""""""""""""""""""""""""""""""""""""""""""""
If your Kraken account is verified with a German address, you will need to accept a trading agreement in order to place market and margin orders.

See https://support.kraken.com/hc/en-us/articles/360036157952

If you agree, fill this value with ``agree``, like this: ``KRAKEN_TRADING_AGREEMENT=agree``

Exchange: Binance
^^^^^^^^^^^^^^^^^

Your Binance API key should hold at least the following permissions:

* Enable Reading
* Enable Spot & Margin Trading
* Enable Withdrawals

You should enable IP access restrictions to use withdrawal through the API. Enter the IP address that matches your outgoing connection. When in doubt, you can check your IP here: https://nordvpn.com/nl/ip-lookup/

BINANCE_API_KEY
"""""""""""""""
This is the identifying part of the API key that you created on the Binance exchange.

**Example**: ``BINANCE_API_KEY=mkYEtmPzI9q9qrwvYzTe44nB495joEM17bhUDspFEkKHjzLmKwT1exvQYxGcL6db``

BINANCE_API_SECRET
""""""""""""""""""
This is the private part of your API connection to Binance. It’s a secret granting access to your Binance account.

**Example**: ``BINANCE_API_SECRET=xXFw9vEiSdgllWfLs55uGC3ZBS3VyZMy1aGj4mYYlIIhX6hQ98AsGsQHLSKI4uj6``

BINANCE_API_URL (optional)
""""""""""""""""""""""""""
The endpoint where the tool should connect to.

**Default**: ``BINANCE_API_URL=https://api.binance.com/``

Feeding configuration into the DCA tool
---------------------------------------

Using a configuration file
^^^^^^^^^^^^^^^^^^^^^^^^^^
When handling multiple environment variables, things can get messy. For easier management you can create a simple configuration file somewhere on your disk and use that to provide the tool with the correct configuration.

For example, creating a new configuration file in your home directory: ``nano /home/username/.bitcoin-dca``

.. include:: ./includes/finding-home-directory.rst

.. code-block:: bash
   :caption: /home/username/.bitcoin-dca

   BL3P_PUBLIC_KEY=....
   BL3P_PRIVATE_KEY=....
   WITHDRAW_ADDRESS=....

Now, when running the tool you can use ``--env-file`` like this:

.. code-block:: bash
   :caption: Providing configuration with Docker's --env-file

   $ docker run --rm -it --env-file=/home/username/.bitcoin-dca ghcr.io/jorijn/bitcoin-dca:latest balance

Using inline arguments
^^^^^^^^^^^^^^^^^^^^^^
For maximum control, you can also feed configuration into the tool like this:

.. note::
   While this gives you more control, it will also allow other people who have access your machine to see the arguments with which you've started the Docker container, thus revealing your API keys.

.. code-block:: bash
   :caption: Providing configuration by specifying each configuration item separately

   $ docker run --rm -it -e BL3P_PUBLIC_KEY=abcd -e BL3P_PRIVATE_KEY=abcd WITHDRAW_ADDRESS=abcd ghcr.io/jorijn/bitcoin-dca:latest balance
