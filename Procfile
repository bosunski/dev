# One process tailing a file, another writing to it
tail: tail -f output.txt
write: while true; do echo "Hello, DEV! The time is $(date)" >> output.txt; sleep 1; done
