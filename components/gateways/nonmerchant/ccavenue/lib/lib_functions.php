<?php
/**
 * LibFunctions Helper functions
 *
 * @package blesta
 * @subpackage blesta.components.gateways.ccavenue.lib
 * @author Phillips Data, Inc.
 * @author Nirays Technologies
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://nirays.com/ Nirays
 */
class LibFunctions{

    /**
     * Construct a new merchant gateway
     */
    public function __construct() {
    }

    /**
     * To verify if the checksum is valid
     * @param $MerchantId
     * @param $OrderId
     * @param $Amount
     * @param $AuthDesc
     * @param $WorkingKey
     * @param $CheckSum
     * @return bool
     */
    public function verifyChecksum($MerchantId , $OrderId, $Amount, $AuthDesc, $WorkingKey,  $CheckSum)
	{
		$str = "$MerchantId|$OrderId|$Amount|$AuthDesc|$WorkingKey";
		$adler = 1;
		$adler = $this->adler32($adler,$str);
		if($adler==$CheckSum) return true;
		else return false;		
	}

    /**
     * To create checksum
     * @param $MerchantId
     * @param $OrderId
     * @param $Amount
     * @param $redirectUrl
     * @param $WorkingKey
     * @return int
     */
    public function getChecksum($MerchantId, $OrderId, $Amount, $redirectUrl, $WorkingKey)  {
		$str = "$MerchantId|$OrderId|$Amount|$redirectUrl|$WorkingKey";
		$adler = 1;
		$adler = $this->adler32($adler,$str);
		return $adler;
	}

    /**
     * Adder logic
     * @param $adler
     * @param $str
     * @return int
     */
    private function adler32($adler , $str)	{
		$BASE =  65521 ;
		$s1 = $adler & 0xffff ;
		$s2 = ($adler >> 16) & 0xffff;
		for($i = 0 ; $i < strlen($str) ; $i++) {
			$s1 = ($s1 + Ord($str[$i])) % $BASE ;
			$s2 = ($s2 + $s1) % $BASE ;
		}
		return $this->leftShift($s2 , 16) + $s1;
	}

    /**
     * Left Shift logic
     * @param $str
     * @param $num
     * @return int
     */
    private function leftShift($str , $num)	{
		$str = DecBin($str);

		for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
			$str = "0".$str ;

		for($i = 0 ; $i < $num ; $i++) {
			$str = $str."0";
			$str = substr($str , 1 ) ;
		}
		return $this->cdec($str) ;
	}

    /**
     * @param $num
     * @return int
     */
    private function cdec($num)	{
		$dec=0;
		for ($n = 0 ; $n < strlen($num) ; $n++)		{
		   $temp = $num[$n] ;
		   $dec =  $dec + $temp*pow(2 , strlen($num) - $n - 1);
		}
		return $dec;
	}

    /**
     * This is used to encrypt the parameter that need to be send.
     * Use this to send encrypted parameters to CCAvenue.
     * @param $plainText
     * @param $key
     * @return string
     */
    public  function encrypt($plainText,$key) {
        $secretKey = $this->hexToBin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);

        /* Open module and Create IV (Intialization Vector) */
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
        $plainPad = $this->padPackets($plainText, $blockSize);

        /* Initialize encryption handle */
        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) {
            /* Encrypt data */
            $encryptedText = mcrypt_generic($openMode, $plainPad);
            mcrypt_generic_deinit($openMode);
        }
        return bin2hex($encryptedText);
    }

    /**
     * Used for decrypting an encrypted Text using the key.
     * @param $encryptedText
     * @param $key
     * @return string
     */
    public function decrypt($encryptedText,$key) {
        $secretKey = $this->hexToBin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText=$this->hexToBin($encryptedText);

        // Open module, and create IV
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        mcrypt_generic_init($openMode, $secretKey, $initVector);
        $decryptedText = mdecrypt_generic($openMode, $encryptedText);

        // Drop nulls from end of string
        $decryptedText = rtrim($decryptedText, "\0");

        // Returns "Decrypted string: some text here"
        mcrypt_generic_deinit($openMode);

        return $decryptedText;
    }

    /**
     * Padding Function
     * @param $plainText
     * @param $blockSize
     * @return string
     */
    private function padPackets ($plainText, $blockSize) {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    /**
     * Hexadecimal to Binary function for php 4.0 version
     * @param $hexString
     * @return string
     */
    private function hexToBin($hexString) {
        $length = strlen($hexString);
        $binString="";
        $count=0;
        while($count<$length) {
            $subString =substr($hexString,$count,2);
            $packedString = pack("H*",$subString);
            if ($count==0) {
                $binString=$packedString;
            } else {
                $binString.=$packedString;
            }
            $count+=2;
        }
        return $binString;
    }

}
?>
