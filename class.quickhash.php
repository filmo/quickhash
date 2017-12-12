<?php
/**	quickHash
*	Calculate a hash based only on 4K of each 4MB section
*	of a file. For our purposes, the likelyhood of corruption
*	going undetected is pretty small. Much faster than MD5
*	when hashing large files. This is only an approximation
* 	useful for detecting incompletely uploaded files from one another.
*	It is not a secure hash as it is only sampling parts of the file.
*
*	It can hash a 3.7GB file in 300 to 500ms 
* 	versus ~93000 ms for regular MD5. (9.3 seconds)
*	
*	@param 	string	$file_path	path to file.
*	@return	string				approximate hash.
*/
class quickHash {

	public $hash_block_size 	= 4096;			// hash only 4kb of data for every 4MB of the file.
	public $skip_size 			= 2048 ** 2;  	// 4MB skip. This is the amount of the file skipped over and not hashed.
	public $debug 				= FALSE;
	public $NL					= "<br/>";		// new line
	public $hash_type			= 'md5';		// supports these algos http://php.net/manual/en/function.hash-algos.php
	
	private $total_file_size 	= 0;
	private $last_end_byte		= 0;
	private $pos				= 0;
	private $path_set 			= false;
	private $ctx;
	private $fh;
	private $path;
	
	/** __construct
	*	Initialize the object with a file path.
	*/
	public function __construct() {
		// set seek head to 0
		$this->pos = 0;
	}
	
	/**	set_path
	*	Set the file path. Must be set prior to hashing. Is considered unset after a
	*	hash is generated. Must be reset for each subsequent file. 
	*	@param	string		path to file
	*/
	public function set_path($file_path) {
		// resets any prior path_set flag. (For when used to hash multiple files from one instance)
		$this->path_set = 	false;
		
		$this->path = $file_path;
		if (is_readable($this->path) && is_file($this->path) && ($this->fh = fopen($this->path, 'rb'))) {
			// great, file is now open and handle stored in $fh
			// lets get the total file size as well.
			$this->total_file_size =  filesize($this->path);
		} else {
			throw new Exception('Could not open "'.$this->path.'" (does not exist, or is not a file)');
		} 
		// set seek head to 0
		$this->pos = 0;
		// everything is set, mark the path_set flag TRUE
		$this->path_set = 	true;
	}
	
	/** get_quickhash
	*	Generate the approximate hash for the file provided.
	*	@return		string		approximate hash
	*/
	public function get_quickhash() {
		if (!$this->path_set) {
			throw new Exception('File path not yet set. Call "set_path" first!');
		}
		// initialize the hash context.
		$this->ctx = hash_init($this->hash_type);

		if ($this->debug) {
			echo "File size: " . $this->total_file_size . $this->NL;
		}		

		$block_count = 0;
		
		while(!feof($this->fh)) {
			// seek to next location. Initially 0.
			$seek_result = fseek($this->fh,$this->pos);	
	
			// read a chunk of binary data. I'm using 4KB
			$bin_data = fread($this->fh, $this->hash_block_size);
			if ($bin_data === False) {
				echo "Bin Data Fail". $this->NL;
				break;
			}
			if (strlen($bin_data) == 0) {
				break;
			}
			// update the hash context with the new data.
			hash_update($this->ctx, $bin_data);
			
			if ($this->debug) {
				$block_count ++;
				// need to use a copy if you're going to debug as hash_final closes out the hash_stream
				$temp_ctx = hash_copy($this->ctx);
				echo "Blk $block_count $pos --> ". ($this->pos + $this->hash_block_size) . " : " . hash_final($temp_ctx). $this->NL;
			}
			// keep track of the last byte that was hashed.
			$this->last_end_byte = ($this->pos + $this->hash_block_size);
			
			// increment the seek position.
			$this->pos += $this->skip_size;
		}
		// close out the file handle.
		fclose($this->fh);
		
		// For uploaded files we're most often most interested in knowing if the tail
		// of the file got cut off between two different uploads.
		if ($this->last_end_byte < $this->total_file_size and $this->total_file_size > $this->hash_block_size) {
			
			if ($this->debug) {
				$remaining_bytes = $this->total_file_size - $this->last_end_byte;
				echo "remaining bytes:". $remaining_bytes . $this->NL;
				echo "seeking to: " . ($this->total_file_size - $this->hash_block_size) . " to read 
					  the last " . $this->hash_block_size ." bytes" . $this->NL;
			}
			
			$this->fh = fopen($this->path,'rb');
			// this can potentially 're-read' some of the prior material that was
			// hased if the block_size is greater than remaning bytes. Doesn't really matter so long
			// as it is consistent. More importantly the last bytes of the file always be hashed since
			// this is what's normally dropped off during a failed TCP/IP upload.
			$seek_result = fseek($this->fh,($this->total_file_size-$this->hash_block_size));
			if ($seek_result === False) {
				echo $NL . "Seek fail" . $this->NL;
				break;
			}
			// read the last $hash_block_size number of bytes in the file. 
			$tail_end_data = fread($this->fh,$this->hash_block_size);
			fclose($this->fh);
			hash_update($this->ctx, $tail_end_data);
		}
		// now that we've read N bytes for every X bytes skipped as well as the last N bytes 
		// at the tail of the file, return the compiled hash.
		return hash_final($this->ctx);
	}
}
?>