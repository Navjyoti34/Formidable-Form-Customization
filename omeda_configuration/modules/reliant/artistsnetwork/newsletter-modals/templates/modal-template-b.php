<?php
/**
 * Template Name: Modal Template B
 * 
**/
?>

<!doctype html >
<html>
	<head><script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				const form = document.querySelector('.mainForm');

				if (form) {
				  form.addEventListener('submit', function(event) {
				    event.preventDefault();

				      if (!form.checkValidity()) {
				        removeElement('.success-message');
				        removeElement('.error-message');
				        addErrorMessage('Please verify your email address for accuracy.');
				        return;
				      }

				    const action = form.getAttribute('action');
					const promocode_element = document.getElementById('promocode');
				    const myInput = document.getElementById('email');

				    const currentDomain = window.location.hostname;
				    const domainParts = currentDomain.split('.');
				    const mainDomain = domainParts.slice(-2).join('.');

				    const email_address = myInput.value;
					const promocode = promocode_element.value;
				    const domain = mainDomain;

				    fetchData(email_address, domain, promocode)
				      .then(response => {
				        removeElement('.success-message');
				        removeElement('.error-message');

				        const content = JSON.parse(response);

				        if(content.error === true) {
				          addErrorMessage(content.msg);
				          return;
				        } else {
							const email = document.getElementById("email").value;
							setCookie("newsletter_subscribed", email, 365);
							addSuccessMessage(content.msg);
							fetchAndReplaceHTML(action);
				        }

				        return;
				      })
				      .catch(error => {
				        removeElement('.error-message');
				        addErrorMessage(error.msg);
				        return;
				      });

				    
				  });
				}

				function setCookie(name, value, days) {
				  const date = new Date();
				  date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
				  const expires = "expires=" + date.toUTCString();
				  document.cookie = name + "=" + value + ";" + expires + ";path=/";
				}

				function addSuccessMessage(msg) {
				  if (!document.querySelector('.success-message')) {
				    const myInput = document.getElementById('email');
				    const successMessageElement = '<div class="success-message"></div>';
				    myInput.insertAdjacentHTML('afterend', successMessageElement);
				  }
				  const successMessage = document.querySelector('.success-message');
				  successMessage.textContent = msg;
				}

				function removeElement(selector) {
				  const element = document.querySelector(selector);
				  if (element) {
				    element.remove();
				  }
				}

				function addErrorMessage(msg) {
				  if (!document.querySelector('.error-message')) {
				    const myInput = document.getElementById('email');
				    const errorMessageElement = '<div class="error-message"></div>';
				    myInput.insertAdjacentHTML('afterend', errorMessageElement);
				  }
				  const errorMessage = document.querySelector('.error-message');
				  errorMessage.textContent = msg;
				}

				function fetchAndReplaceHTML(action) {
				  const xhr = new XMLHttpRequest();
				  xhr.open('GET', action, true);
				  xhr.onreadystatechange = function() {
				    if (xhr.readyState === 4 && xhr.status === 200) {
				      const html = xhr.responseText;
				      document.documentElement.innerHTML = html;
				    }
				  };
				  xhr.send();
				}

				function fetchData(email_address, domain, promocode) {
				  return new Promise((resolve, reject) => {
				    const xhr = new XMLHttpRequest();
				    const url = 'https://mag.goldenpeakmedia.com/?newsletter_api=true' + '&email=' + email_address + '&promocode=' + promocode + '&domain=' + domain + '#';

				    xhr.open('GET', url, true);
				    xhr.onreadystatechange = function() {
				      if (xhr.readyState === 4) {
				        if (xhr.status === 200) {
				          resolve(xhr.responseText);
				        } else {
				          const error = {
				            err: true,
				            msg: 'Unfordtunately, we are unable to process your newsletter signup at this time.'
				          };
				          reject(error);
				        }
				      }
				    };
				    xhr.send();
				  });
				}
			});
		</script>
		<style>
			.background-container{
				/*background-image:url(https://www.quiltingdaily.com/wp-content/uploads/2023/05/QuiltingDaily_Horiztonal_4C_Logo-01.png);*/
				background-position:center;
				background-repeat:no-repeat;
				background-size:50%;
			}

             .signup-form {
      display: flex;
      flex-direction: column;
      align-items: center;
      max-width: 77%;
      margin: 0 auto;
      gap: 10px;
    }

    .signup-input {
      padding: 10px;
      border: 1px solid #ddd;
      font-size: 18px;
      border-radius: 4px;
      width:92%;
    }

            .signup-button {
			      display: inline-block;
			      padding: 10px 20px;
			      font-size: 16px;
			      background-color: #1E73BE !important;
			      font-weight:  700;
			      width:100%;
			      color: #fff;
			      border: none;
			      border-radius: 4px;
			      text-decoration: none;
			      cursor: pointer;
			      letter-spacing: 1px;
			      transition: background-color 0.3s ease;
    				margin-bottom: 5px;
					border: 2px solid #1E73BE !important;
			    }

			    .signup-button:hover {
				  background-color: #FFF !important;
			      color: #1E73BE !important;
			      border: 2px solid #1E73BE !important;
			    }
            
			body {
				width:100%;
				font-family: "Roboto Flex",sans-serif;
				background-color:#ffffff;
			}
			body {
				width:100%;
				font-family:"Open Sans", Sans-Serif;
				background-color:#ffffff;
			}
			.modalLeft{
				width:50%;
				float:left;
			}
            
			.modalRight {
    width: 50%;
    float: right;
    position: absolute;
    right: 0;
    padding-top: 15px;
}
            
                @media screen and (max-width: 700px) {
            	.modalLeft{
				width:100%;
				float:none;
			}
            
			.modalRight{
				width:100%;
				float:none;
			}
            
            .background-container{
            background-size:75%;
            }
            }
            
            @media screen and (max-width: 400px) {
              .background-container{
            background-size:100%;
            }
            }
            
			.logoContainer{
				text-align:center;
			}
			.logo{
				max-width:50%;
				padding:10px;
			}
            
            @media screen and (max-width: 700px) {
            .logo{
            padding: 50px 0 0;
            }
            }
            
			.taglineOuter{
				    text-align: center;
                /*background-color:#4d61b5;*/
				background-image:url(https://www.artistsnetwork.com/wp-content/uploads/2023/08/Untitled-design-5.png);
    background-position: center;
    background-repeat: no-repeat;
    width: 45%;
    border-radius: 0;
    height: 100%;
    background-blend-mode: soft-light;
    position: absolute;
    top: 0;
	left: 30px;
   
			}
			.taglineInner{
				border-radius:5px;
				height:500px;

			}
			.taglineText{
				color:white;
				font-size:40px;
				padding:20px;
				text-shadow: 2px 2px #000000;
				padding-top:20%;
				line-height:70px;
			}
			.cta{
				text-align: center;
				padding: 5px 30px 20px 0px;
				font-size: 23px;
			}
			.mainForm{
				text-align:center;
			}
			.emailField{
				min-height:30px;
				width:60%
			}
			.submitButton{
				color:#ffffff;
				background-color:#4d61b5;
				width:70%;
				min-height:40px;
				border-radius:5px;
				margin:30px;
			}
			.disclaimer{
				font-size: 10px;
				text-align: center;
				width: 95%;
				margin: 0 auto;
				margin-top: 5px;
			}
            @font-face{
				font-family: Open Sans; 
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-Regular.ttf) format('truetype'); 
				font-weight: 400; 
				font-style: normal; 
			} 
			@font-face{ 
				font-family: Open Sans; 
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-Italic.ttf) format('truetype'); 
				font-weight: 400; font-style: italic;
			}
			@font-face{
				font-family: Open Sans;
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-Bold.ttf) format('truetype'); 
				font-weight: 700; 
				font-style: normal;
			}
			@font-face{
				font-family: Open Sans;
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-BoldItalic.ttf) format('truetype');
				font-weight: 700; 
				font-style: italic;
			}
			@font-face{
				font-family: Open Sans;
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-Light.ttf) format('truetype');
				font-weight: 300; 
				font-style: normal;
			}
			@font-face{
				font-family: Open Sans;
				src: url(https://s31968.pcdn.co/wp-content/uploads/2022/06/OpenSans-LightItalic.ttf) format('truetype');
				font-weight: 300;
				font-style: italic;
			}
			.error-message {
			  color: #dc3545;
			  font-size: 13px;
			  margin-top: 5px;
			}

			.success-message {
			  color: #28a745;
			  font-size: 13px;
			  margin-top: 5px;
			}
		</style>	
	</head>
	<body>
	<div class="background-container">
		<div class="modalLeft">
			<div class="taglineOuter">
				<div class="taglineInner">
						<div class="taglineText">
							<br/><br/>
						</div>
				</div>
			</div>
		</div>
		<div class="modalRight">
			<div class="logoContainer">
				<img class="logo" src="https://www.artistsnetwork.com/wp-content/uploads/2023/05/anlogo.png">
			</div>
			<div class="cta">
				<strong>Sign up for newsletters from Artists Network today,</strong> and in addition to daily tips and techniques, you'll get an instant <strong>20% off</strong> one item in our store!
			</div>
			<form class="mainForm signup-form" action="/modal-thank-you">
				<input type="text" class="signup-input" id="email" name="email" placeholder="Enter your email">
				<input type="hidden" id="promocode" name="promocode" value="arnleftimg20">
				<input class="signup-button" type="submit" value="Sign me up!">
			</form>
			<div class="disclaimer">
				*I agree to receive emails from <strong><?php echo get_bloginfo('name'); ?></strong>, including educational resources, promotions, partner news and tips. I can unsubscribe at any time. For details on our data practices, visit our <a href="https://goldenpeakmedia.com/privacy-policy" target="_blank">Privacy Policy</a>. Discount exclusions apply.
			</div>
		</div>
	</div>
	</body>
</html>
