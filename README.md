## aido-aifix
Command-line utilities for AI-driven development and troubleshooting

- **aido** is your command-line AI assistant. Describe your goal in plain English — it figures out what to do *and actually does it*. That might mean **generating code**, **installing packages**, **creating files**, **running commands** — or all of the above.

- **aifix** is your automated troubleshooter. It takes a broken CLI command (like a failed compile), analyzes the error, and keeps editing code or adjusting the environment until the issue is resolved.

## ✨ Features

- ⚡ Just tell `aido` what you want — it makes it happen, step by step
- 📦 Automatically installs missing packages, creates files, edits code
- 🛠️ `aifix` resolves broken CLI commands by editing the relevant code or environment, until the problem is resolved.
  _Stop chasing cryptic errors — let AI fix them._
- 💬 Interactive when needed, but often fully hands-free

## 🧪 Example: Fixing a Broken Command with `aifix`
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
(...)
```

## ⚙️ Example: Creating a Web Project with `aido`
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

## 🧰 Requirements

- **PHP** 8.0 or newer
- **OpenAI API key** (with access to models like `o3-mini`)
- **Unix-like OS** (Linux, macOS, *BSD)

# Installation
```bash
git clone --recurse-submodules --depth=1 https://github.com/divinity76/aido-aifix.git
cd aido-aifix
sudo ln -sv "$(pwd)/src/aido.php" /usr/local/bin/aido
sudo chmod a+x /usr/local/bin/aido
sudo ln -sv "$(pwd)/src/aifix.php" /usr/local/bin/aifix
sudo chmod a+x /usr/local/bin/aifix
mkdir -p ~/.config/
echo '{"api_key":"OpenAI-api-key-here", "default_model":"o3-mini"}' > ~/.config/aido.json
```

### 🤖 AI Model Comparison
| Model      | Cost vs o3-mini | Quality       | Notes                                  |
|------------|------------------|---------------|-----------------------------------------|
| `o3-mini`  | 1x               | ✅ Great       | Default choice — solid balance of cost and performance |
| `o1`       | 13.6x            | 🔥 Best        | Use for the toughest problems — very capable |
| `4o`       | 2.27x            | ⚠️ Meh         | Not worth the extra cost — often worse than `o3-mini` |
| `4o-mini`  | 0.14x            | 💤 Lazy        | Very cheap, but tends to apply and report unverified fixes |

- **4o**: Not recommended. 2.27x more expensive than o3-mini, and seems to do *worse*, or no better, than o3-mini.

- **4o-mini**: Not recommended. is the cheapest option, cost only 14% of o3-mini, but it's "lazy"—it may assume it found the solution, apply it, and report success without actually verifying. Not recommended :( (it is the cheapest way to test aifix / aido, tho)
