window.Espees = (function () {
  function init(config) {
    const {
      amount,
      sku,
      narration,
      merchant_wallet,
      success_url,
      fail_url,
      token
    } = config;
    const params = new URLSearchParams(window.location.search);
    const proxy_url = "/espees_payment_integration/backend/espees_proxy.php"
    const container = document.getElementById("espees-button");
    if (!container) {
      console.error("Espees SDK: no container with id 'espees-button'");
      return;
    }
    
    const button = document.createElement("button");
    button.innerText = "Pay with Espees";
    const bimag = document.createElement('img')
    bimag.src='./../espees_devappIconsmall.svg'
    bimag.style.width = '30px'
    bimag.style.height = '30px'
    bimag.style.marginLeft ='6px'
    button.appendChild(bimag)
    Object.assign(button.style, {
      display: "flex",
      justifyContent: "center",
      width:"100%",
      fontWeight: "400",
      textAlign: "center",
      whiteSpace: "nowrap",
      verticalAlign: "middle",
      userSelect: "none",
      border: "1px solid transparent",
      padding: "0.775rem 0.75rem",
      fontSize: "1rem",
      fontWeight:"900",
      lineHeight: "1.5",
      borderRadius: "0.575rem",
      transition: "all 0.15s ease-in-out",
      textDecoration: "none",
      cursor: "pointer",
      color: "#fff",
      backgroundColor: "#250101",
      borderColor: "#250101",
    });

    // Add hover effect using JavaScript
    button.addEventListener("mouseover", () => {
      button.style.backgroundColor = "#250101";
      button.style.borderColor = "#250101";
    });

    button.addEventListener("mouseout", () => {
      button.style.backgroundColor = "#250101";
      button.style.borderColor = "#250101";
    });

   
    container.appendChild(button);

    button.addEventListener("click", function () {
      fetch(proxy_url + "?action=initiate", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_sku: sku,
          narration: narration,
          price: amount,
          merchant_wallet: merchant_wallet,
          success_url: success_url,
          fail_url: fail_url,
          token:token,
          user_data: {
            param1: "value1",
            param2: "value2"
          }
        })
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.statusCode === 200 && data.payment_ref) {
            window.location.href = `https://payment.espees.org/pay/${data.payment_ref}`;
          } else {
            alert("Payment initiation failed: " + (data.message || "Unknown error"));
            console.error("Espees SDK error:", data);
          }
        })
        .catch((err) => {
          console.error("Error contacting proxy:", err);
          alert("Error starting payment.");
        });
    });
  }

  return { init };
})();
