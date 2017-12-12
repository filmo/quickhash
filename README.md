# quickhash
Fast non-cryptographically secure file hash approximation

This provides an approximate hash for large files by reading N bytes for every X bytes of a file. Typically it hashes 4KB for every 2MB of a file. To cover the end of files it also hashes the last 4KB of the file.

For very large files, this is much faster than creating a hash for the entire file, and under certain assumptions, just as useful.

The main assumption is that your use case has no need for cryptographically secure hashes as it would be easy to craft colisions with intent. 

The main use is to create a 'relatively' unique hash for a file that can help in identifying 'duplicate' files where you're not worried about bad actors. For example, you may receive largeish (1GB+) video clips that you need to hash quickly for a responsive app to determine if you've already seen that file or not before. This code will create an approximate MD5 hash about 40x to 60x faster than a full MD5 hash. By varying the amount of N bytes hashed and X bytes skipped, you can adjust to your use case.

I've made PHP and Perl versions, but basic idea could be easily expanded.
