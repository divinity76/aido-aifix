# aido-aifix
aido and aifix: Command-line utilities for AI-driven development and troubleshooting

# Example
```
hans@LAPTOP-O1AO16UE:/wtf$ aido make a hello world c++ program
(...)
>I have created a "Hello, World!" C++ program and saved it as `hello_world.cpp`. If you need further assistance or want to compile and run the program, please let me know!"

hans@LAPTOP-O1AO16UE:/wtf$ cat hello_world.cpp 
#include <iostream>

int main() {
    std::cout << "Hello, World!" << std::endl;
    return 0;
}
hans@LAPTOP-O1AO16UE:/wtf$ echo syntaxerror >> hello_world.cpp 
hans@LAPTOP-O1AO16UE:/wtf$ g++ hello_world.cpp 
hello_world.cpp:6:2: error: ‘syntaxerror’ does not name a type
    6 | }syntaxerror
      |  ^~~~~~~~~~~

hans@LAPTOP-O1AO16UE:/wtf$ aifix g++ hello_world.cpp 
(...)
"Please analyze and resolve the following issue using all available tools if needed:
Issue Details:
array(4) {
  ["command"]=>
  string(19) "g++ hello_world.cpp"
  ["exit_code"]=>
  int(1)
  ["stdout"]=>
  string(0) ""
  ["stderr"]=>
  string(109) "hello_world.cpp:6:2: error: ‘syntaxerror’ does not name a type
    6 | }syntaxerror
      |  ^~~~~~~~~~~
"
(...)
"The issue in the provided code has been resolved. The extraneous text `syntaxerror` was removed from the `hello_world.cpp` file, and the code compiled successfully.
If you need any further assistance or have additional requests, feel free to ask!"
hans@LAPTOP-O1AO16UE:/wtf$ g++ hello_world.cpp 
hans@LAPTOP-O1AO16UE:/wtf$ ./a.out 
Hello, World!
```

# Requirements
- php-cli >=8
- OpenAI API key
- unix system (Linux/MacOS)
# Installation
```
sudo ln -sv "$(pwd)/src/aido.php" /usr/local/bin/aido
sudo chmod a+x /usr/local/bin/aido
sudo ln -sv "$(pwd)/src/aifix.php" /usr/local/bin/aifix
sudo chmod a+x /usr/local/bin/aifix
```
