<?php
/**
 * Template Name: Modal Thank You
 * 
**/

$hide_second_paragraph = '';

if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    
    $referer = rtrim($referer, '/');
    
    $parts = explode('/', $referer);
    $lastPart = end($parts);
    
    if(!($lastPart == 'modal-template')) {
    	$hide_second_paragraph = ' style="display:none !important;"';
    }
}

?>

<!doctype html >
<html>
	<head>
		<style>
			.background-container{
				/*background-image:url();*/
				background-position:center;
				background-repeat:no-repeat;
				background-size:50%;
			}
            
            @media screen and (max-width: 700px) {
            .background-container{
            background-size:75%;
            }
            }
            
               @media screen and (max-width: 400px) {
            .background-container{
            background-size:100%;
            }
            }
            
			body {
				width:100%;
				font-family: "Roboto Flex",sans-serif;
				background-color:#ffffff;
			}
			.logoContainer{
				text-align:center;
			}
			.logo{
				max-width:50%;
				padding:10px;
			}
			.taglineOuter{
				text-align:center;
				background-image:url(https://www.artistsnetwork.com/wp-content/uploads/2023/08/Artists-Network-horizontal-2.png);
				background-position:center;
				background-repeat:no-repeat;
				width:90%;
				border-radius:5px;
				margin:auto;
			}
			.taglineInner{
				/*background-color:#1E73BE;*/
				opacity:70%;
				border-radius:5px;
			}
			.taglineText{
				color:white;
				font-size:30px;
				padding:20px;
				text-shadow: 2px 2px #000000;
			}
			.cta{
				text-align:center;
				padding:40px;
				font-size:25px;
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
				background-color:#1E73BE;
				width:70%;
				min-height:40px;
				border-radius:5px;
				margin:30px;
			}
			.disclaimer{
				font-size:10px;
				text-align:center;
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

			.background-container {
				margin: 0 auto;
				display: flex;
				justify-content: center;
				align-items: center;
				flex-direction: column;
				height: 100vh;
			}

			.taglineOuter {
				display: none;
			}

			.logoContainer {
				width: 75%;
			}

			.cta {
				padding-top: 0px;
			}
		</style>	
	</head>
	<body>
	<div class="background-container">
		<div class="logoContainer">
			<img class="logo" src="https://www.artistsnetwork.com/wp-content/uploads/2023/05/anlogo.png">
		</div>
		<div class="taglineOuter">
			<div class="taglineInner">
					<div class="taglineText">
						<br/><br/>
					</div>
			</div>
		</div>
		<div class="cta">
		<p>Welcome to the community! You'll start receiving our inspiring Artists Network newsletters shortly.</p>
		<p<?php echo $hide_second_paragraph; ?>>In the meantime, use the promo code <strong>ARTIST20</strong> to take 20% off the next item you purchase in our store! (Some exclusions apply.)</p>
      
		</div>
	</div>
	</body>
</html>
