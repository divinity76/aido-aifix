## aido-aifix
Command-line utilities for AI-driven development and troubleshooting

- **aido** is an AI assistant you can talk to from the command line. Describe what you want to do in plain language — and it will figure it out *and actually do it* for you. That might mean generating code, installing packages, creating files, running commands, or all of the above.
  
- **aifix** takes a broken command (like a failed compile), analyzes the error, and automatically edits your files or environment and re-runs the command, until the issue is resolved.

## ✨ Features

- ⚡ Just tell `aido` what you want — it makes it happen, step by step
- 📦 Automatically installs missing packages, creates files, edits code
- 🛠️ `aifix` repairs broken CLI commands by editing the relevant code or setup
- 💬 Interactive when needed, but often fully hands-free

# Example
Here’s how **aifix**  handled a broken `apt install` by suggesting and guiding through an alternative installation method for `wscat` on Ubuntu.  
_Model used: `o3-mini`_
```bash
hans@LAPTOP-O1AO16UE:/wtf$ wscat
Command 'wscat' not found, but can be installed with:
sudo apt install node-ws
hans@LAPTOP-O1AO16UE:/wtf$ sudo apt install node-ws
Reading package lists... Done
Building dependency tree... Done
Reading state information... Done
Some packages could not be installed. This may mean that you have
requested an impossible situation or if you are using the unstable
distribution that some required packages have not yet been created
or been moved out of Incoming.
The following information may help to resolve the situation:

The following packages have unmet dependencies:
 node-ws : Depends: node-https-proxy-agent (>= 7) but it is not going to be installed
           Depends: nodejs:any
E: Unable to correct problems, you have held broken packages.
hans@LAPTOP-O1AO16UE:/wtf$ aifix wscat
(...debug output...)
The error encountered indicates that the 'wscat' command is not installed. Would you like me to install 'wscat' globally via npm (npm install -g wscat) to resolve this issue?
Your response: yes
(...debug output...)
The installation using 'npm install -g wscat' failed due to permission issues (EACCES). Would you like me to try running the installation command with sudo (i.e., 'sudo npm install -g wscat') to attempt a global installation?
Your response: yes
(...debug output...)
string(485) "The issue was that the \"wscat\" command was not found because it was not installed. After asking for confirmation, I attempted to install it globally via npm. The initial installation attempt failed due to permission issues (EACCES error). Upon further clarification, I then executed \"sudo npm install -g wscat\", which successfully installed wscat. Finally, I confirmed that the command is now available by running \"wscat --version\", which returned \"6.0.1\". 

The issue is now resolved."
hans@LAPTOP-O1AO16UE:/wtf$ wscat
Usage: wscat [options] (--listen <port> | --connect <url>)

Options:
  -V, --version                       output the version number
  --auth <username:password>          add basic HTTP authentication header
  --ca <ca>                           specify a Certificate Authority (--connect only)
  --cert <cert>                       specify a Client SSL Certificate (--connect only)
  --host <host>                       optional host
  --key <key>                         specify a Client SSL Certificate's key (--connect only)
  --max-redirects [num]               maximum number of redirects allowed (default: 10)
  --no-color                          run without color
  --passphrase [passphrase]           specify a Client SSL Certificate Key's passphrase (--connect only). If you don't provide a value, it will be prompted for
  --proxy <[protocol://]host[:port]>  connect via a proxy. Proxy must support CONNECT method
  --slash                             enable slash commands for control frames (/ping [data], /pong [data], /close [code [, reason]]) (--connect only)
  -c, --connect <url>                 connect to a WebSocket server
  -H, --header <header:value>         set an HTTP header. Repeat to set multiple (--connect only) (default: [])
  -l, --listen <port>                 listen on port
  -L, --location                      follow redirects
  -n, --no-check                      do not check for unauthorized certificates (--connect only)
  -o, --origin <origin>               optional origin
  -p, --protocol <version>            optional protocol version
  -P, --show-ping-pong                print a notification when a ping or pong is received (--connect only)
  -s, --subprotocol <protocol>        optional subprotocol. Repeat to specify more than one (default: [])
  -w, --wait <seconds>                wait given seconds after executing command
  -x, --execute <command>             execute command after connecting (--connect only)
  -h, --help                          display help for command
```

Another example (not a real-life example):
```bash
# Using aido to automate the setup of a small web project
hans@LAPTOP-O1AO16UE:/projects$ aido create a new web project with HTML, CSS, and JavaScript
(...execution output...)
> I have set up a new web project with the files `index.html`, `styles.css`, and `script.js`.

# Displaying the structure of the created project
hans@LAPTOP-O1AO16UE:/projects$ tree new-web-project
new-web-project
├── index.html
├── styles.css
└── script.js

# Intentionally adding a syntax error in JavaScript
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ echo 'consol.log("Test")' >> script.js

# Simulating the error by running the script through Node.js
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ node script.js
(...execution output...)
> ReferenceError: consol is not defined

# Using aifix to automatically resolve the syntax error
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ aifix node script.js
(...execution output...)
> The issue has been resolved. The text `consol` was corrected to `console`.

# Running the corrected JavaScript file
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ node script.js
Test
```

# Requirements
- php-cli >=8
- OpenAI API key
- unix system (Linux/MacOS)
# Installation
```bash
git clone --recurse-submodules --depth=1 https://github.com/divinity76/aido-aifix.git
cd aido-aifix
sudo ln -sv "$(pwd)/src/aido.php" /usr/local/bin/aido
sudo chmod a+x /usr/local/bin/aido
sudo ln -sv "$(pwd)/src/aifix.php" /usr/local/bin/aifix
sudo chmod a+x /usr/local/bin/aifix
```

## AI Models

- **Default Model - o3-mini**: Cost-effective and suitable for general tasks.

- **Premium Model - o1**: Use for the most challenging issues; it's approximately 13.6 times more expensive than o3-mini.

- **4o**: 2.27x more expensive than o3-mini, and seems to do *worse*, or no better, than o3-mini.

- **4o-mini** is the cheapest option, cost only 14% of o3-mini, but it's "lazy"—it may assume it found the solution, apply it, and report success without verifying the issue is truly resolved. Not recommended :( (it is the cheapest way to test aifix / aido, tho)

