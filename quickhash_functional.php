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
*	@param 	string	$path	path to file.
*	@return	string			approximate hash.
*/
function quickHash($path) {

	$hash_block_size 	= 4096;			// hash only 4kb of data for every 4MB of the file.
	$skip_size 			= 2048 ** 2;  	// 4MB skip
	$total_file_size 	= filesize($path);
	$hash_type			= 'md5';
	$debug 				= FALSE;
	$NL					= "<br/>";		// new line
	
	if ($debug) echo "File size: " . $total_file_size . $NL;
	
	// initialize the hashing context
	$ctx = hash_init($hash_type);
	// set the beginning seek location
	$pos = 0;
	
	$fh = fopen($path,'rb');
	if (!is_resource($fh)) {
		if ($debug) echo "Invalid file handle". $NL;
		return false
	}
	
	while(!feof($fh)) {
		// seek to next location. Initially 0.
		$seek_result = fseek($fh,$pos);	

		// read a chunk of binary data. I'm using 4KB
		$bin_data = fread($fh, $hash_block_size);
		if ($bin_data === False) {
			echo "Bin Data Fail". $NL;
			break;
		}
		if (strlen($bin_data) == 0) {
			break;
		}
		// update the hash context with the new data.
		hash_update($ctx, $bin_data);
		
		if ($debug) {
			$block_count ++;
			// need to use a copy if you're going to debug as hash_final closes out the hash_stream
			$temp_ctx = hash_copy($ctx);
			echo "Blk $block_count $pos --> ". ($pos + $hash_block_size) . " : " . hash_final($temp_ctx). $NL;
		}
		// keep track of the last byte that was hashed.
		$last_end_byte = ($pos + $hash_block_size);
		
		// increment the seek position.
		$pos += $skip_size;
	}
	// close out the file handle.
	fclose($fh);
	
	// For uploaded files we're most often most interested in knowing if the tail
	// of the file got cut off between two different uploads.
	if ($last_end_byte < $total_file_size and $total_file_size > $hash_block_size) {
		
		if ($debug) {
			$remaining_bytes = $total_file_size - $last_end_byte;
			echo "remaining bytes:". $remaining_bytes . $NL;
			echo "seeking to: " . ($total_file_size-$hash_block_size) . $NL;
		}
		
		$fh = fopen($path,'rb');
		// this can potentially 're-read' some of the prior material that was
		// hased if the block_size is greater than remaning bytes. Doesn't really matter so long
		// as it is consistent. More importantly the last bytes of the file always be hashed since
		// this is what's normally dropped off during a failed TCP/IP upload.
		$seek_result = fseek($fh,($total_file_size-$hash_block_size));
		if ($seek_result === False) {
			echo $NL . "Seek fail" . $NL;
			break;
		}
		// read the last $hash_block_size number of bytes in the file. 
		$tail_end_data = fread($fh,$hash_block_size);
		fclose($fh);
		hash_update($ctx, $tail_end_data);
	}
	return hash_final($ctx);
}
?>