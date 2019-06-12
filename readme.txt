CERTIFIQUE-SE QUE:

- tem a livraria SOAP para PHP ativa
- tem a API REST do Woocommerce ativa


CANCELAMENTO DE REFERÊNCIAS MULTIBANCO


Para cancelar as ordens com referências expiradas configurar um cronjob
para correr todos os dias (por exemplo às 12:00)

Endereço
http://link_da_loja/wc-api/WC_HipayMultibanco/?order=cancel

Alterar "http://link_da_loja" para o link da loja

Os stocks são atualizados (incrementados com a quantidade reservada) caso não tenha optado pela atualização após confirmação de pagamento.

Se o endereço não funcionar coloque em alternativa

http://link_da_loja/wc-api/WC_HipayMultibanco/index.php?order=cancel
