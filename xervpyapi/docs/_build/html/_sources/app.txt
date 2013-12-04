app Module
==========


Api methods
-----------

.. autoflask:: app:app
   :undoc-static:
   :endpoints:


.. http:get:: /url

   404 handler

    
    **Example response**:

    .. sourcecode:: http

      HTTP/1.1 404 Not Found
      Content-Type    application/json

      {'status': 'error', 'message': 'Not found: url'}

    :code 404: unknown url

Common methods
--------------

.. automodule:: app
    :members:
    :undoc-members:
    :show-inheritance:
