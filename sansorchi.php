<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
header('Content-type: application/json');
class BinaryStream
{
    /**
     * @var string Byte stream
     */
    protected $_stream;

    /**
     * @var int Length of stream
     */
    public $_streamLength;

    /**
     * @var bool BigEndian encoding?
     */
    protected $_bigEndian;

    /**
     * @var int Current position in stream
     */
    protected $_needle;

    /**
     * Constructor
     *
     * Create a reference to a byte stream that is going to be parsed or created
     * by the methods in the class. Detect if the class should use big or
     * little Endian encoding.
     *
     * @param  string $stream use '' if creating a new stream or pass a string if reading.
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($stream)
    {
        if (!is_string($stream)) {
            throw new Exception\InvalidArgumentException('Inputdata is not of type String');
        }

        $this->_stream       = $stream;
        $this->_needle       = 0;
        $this->_streamLength = strlen($stream);
        $this->_bigEndian    = (pack('l', 1) === "\x00\x00\x00\x01");
    }

    /**
     * Returns the current stream
     *
     * @return string
     */
    public function getStream()
    {
        return $this->_stream;
    }

    /**
     * Read the number of bytes in a row for the length supplied.
     *
     * @todo   Should check that there are enough bytes left in the stream we are about to read.
     * @param  int $length
     * @return string
     * @throws Exception\LengthException for buffer underrun
     */
    public function readBytes($length)
    {
        if (($length + $this->_needle) > $this->_streamLength) {
           // throw new Exception\LengthException('Buffer underrun at needle position: ' . $this->_needle . ' while requesting length: ' . $length);
        }
        $bytes = substr($this->_stream, $this->_needle, $length);
        $this->_needle+= $length;
        return $bytes;
    }

    /**
     * Write any length of bytes to the stream
     *
     * Usually a string.
     *
     * @param  string $bytes
     * @return BinaryStream
     */
    public function writeBytes($bytes)
    {
        $this->_stream.= $bytes;
        return $this;
    }

    /**
     * Reads a signed byte
     *
     * @return int Value is in the range of -128 to 127.
     * @throws Exception\UnderflowException
     */
    public function readByte()
    {
        if (($this->_needle + 1) > $this->_streamLength) {
          //  throw new Exception\UnderflowException('Buffer underrun at needle position: ' . $this->_needle . ' while requesting length: 1');
        }

        return ord($this->_stream[$this->_needle++]);
    }

    /**
     * Writes the passed string into a signed byte on the stream.
     *
     * @param  string $stream
     * @return BinaryStream
     */
    public function writeByte($stream)
    {
        $this->_stream.= pack('c', $stream);
        return $this;
    }

    /**
     * Reads a signed 32-bit integer from the data stream.
     *
     * @return int Value is in the range of -2147483648 to 2147483647
     */
    public function readInt()
    {
        return ($this->readByte() << 8) + $this->readByte();
    }

    /**
     * Write an the integer to the output stream as a 32 bit signed integer
     *
     * @param  int $stream
     * @return BinaryStream
     */
    public function writeInt($stream)
    {
        $this->_stream.= pack('n', $stream);
        return $this;
    }

    /**
     * Reads a UTF-8 string from the data stream
     *
     * @return string A UTF-8 string produced by the byte representation of characters
     */
    public function readUtf()
    {
        $length = $this->readInt();
        return $this->readBytes($length);
    }

    /**
     * Wite a UTF-8 string to the outputstream
     *
     * @param  string $stream
     * @return BinaryStream
     */
    public function writeUtf($stream)
    {
        $this->writeInt(strlen($stream));
        $this->_stream.= $stream;
        return $this;
    }


    /**
     * Read a long UTF string
     *
     * @return string
     */
    public function readLongUtf()
    {
        $length = $this->readLong();
        return $this->readBytes($length);
    }

    /**
     * Write a long UTF string to the buffer
     *
     * @param  string $stream
     * @return BinaryStream
     */
    public function writeLongUtf($stream)
    {
        $this->writeLong(strlen($stream));
        $this->_stream.= $stream;
    }

    /**
     * Read a long numeric value
     *
     * @return double
     */
    public function readLong()
    {
        return ($this->readByte() << 24) + ($this->readByte() << 16) + ($this->readByte() << 8) + $this->readByte();
    }

    /**
     * Write long numeric value to output stream
     *
     * @param  int|string $stream
     * @return BinaryStream
     */
    public function writeLong($stream)
    {
        $this->_stream.= pack('N', $stream);
        return $this;
    }

    /**
     * Read a 16 bit unsigned short.
     *
     * @todo   This could use the unpack() w/ S,n, or v
     * @return double
     */
    public function readUnsignedShort()
    {
        $byte1 = $this->readByte();
        $byte2 = $this->readByte();
        return (($byte1 << 8) | $byte2);
    }

    /**
     * Reads an IEEE 754 double-precision floating point number from the data stream.
     *
     * @return double Floating point number
     */
    public function readDouble()
    {
        $bytes = substr($this->_stream, $this->_needle, 8);
        $this->_needle+= 8;

        if (!$this->_bigEndian) {
            $bytes = strrev($bytes);
        }

        $double = unpack('dflt', $bytes);
        return $double['flt'];
    }

    /**
     * Writes an IEEE 754 double-precision floating point number from the data stream.
     *
     * @param  string|double $stream
     * @return BinaryStream
     */
    public function writeDouble($stream)
    {
        $stream = pack('d', $stream);
        if (!$this->_bigEndian) {
            $stream = strrev($stream);
        }
        $this->_stream.= $stream;
        return $this;
    }

}
function urlsafeB64Decode($input)
{
    $remainder = \strlen($input) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $input .= \str_repeat('=', $padlen);
    }
    return \base64_decode(\strtr($input, '-_', '+/'));
}
function httpGet($url)
{
    $ch = curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    
    curl_setopt($ch,CURLOPT_HEADER, false);

    $output=curl_exec($ch);
    curl_close($ch);
    return $output;
}
function decrypt($ciphertext,$key,$iv) {
    $method = "AES-128-CBC";

    return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
}

$enc = httpGet("https://raw.githubusercontent.com/sansorchi/sansorchi/main/mydata.txt");
$sp = "bKQscagKGOtj";
$ex = explode($sp, $enc, 2);
$ex2 = explode(strrev($sp), $ex[1], 2);
$str = $ex[0] . $ex2[1];

$enc = urlsafeB64Decode($str);

$stream = new BinaryStream($enc);

$version   = $stream->readByte();
$timestamp   = $stream->readLong();
$timestamp   = $stream->readLong();

$iv  = $stream->readBytes(16);

$chipertext  = $stream->readBytes(strlen($enc) - 57);
//$key = bin2hex($stream->readBytes(32));

$key = substr(urlsafeB64Decode($ex2[0]),16);

$dec = urlsafeB64Decode(decrypt($chipertext,$key,$iv));

$new_text = preg_replace_callback('/\b([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\b/', function ($matches) {
    $parts = explode('-', $matches[1], 3);
    $parts[1] = '0f23';
    return implode('-', $parts);
}, $dec);
error_reporting(E_ERROR | E_PARSE);

if ($_GET["sub"] == "1"){
    
die(base64_encode($new_text));
}
die(($new_text));

?>

<html>
  <head>      
  </head>
  <body>
    <?php echo $message; ?>
  </body>
</html>
