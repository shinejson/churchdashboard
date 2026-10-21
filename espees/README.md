# Espees Integration (Pure JS + PHP Proxy)

## Setup
Quick set up

1. Adding the script to your files.
<code>
  Paste this script on the body of the page you want uses to pay with espees
  <script src="url?token=<your token>"> 
  </code>

## Locally hosted setup.

1. Download the zip file of the pluggin from the from here
   <a href=""></a>

2. Unzip the zip file and place it on your root directory
3. Replace placeholders token:
   - token = `<your-token>`


## Usage
   <code>
     <div id="espees-button"></div>
      set width as need for your design.
      
Initialise the function in your script like this:
 <script>
    Espees.init({
      amount: "amount",
      sku: "<Product_identity>",
      narration: "<Example Product>",
      merchant_wallet: "Merchant-wallet-address",
      success_url: "success.html",
      fail_url: "failure.html",
      token : "<your token>"
    });
  </script> 
  and that's it. Users can click the button to make payment.
</code> 



## Security Recommendations

- Limit `Access-Control-Allow-Origin` to your domain instead of `*` in production.
- Protect your API key.
- Use HTTPS.

## Licence / Notes
- Licence to Newmedia inc. alrights reserved.
- No changes should be made to the code except with express permission from newmedia inc.
