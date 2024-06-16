{{--
    This file is used to output the shell eval command for the shadowenv
    command. This is used to load the shadowenv environment variables into
    the current shell session.

    See: https://shopify.github.io/shadowenv/getting-started/#add-to-your-shell-profile
--}}
# Shadow Env
@if ($shell === 'bash')
eval "$(shadowenv init bash)"
@endif
@if ($shell === 'zsh')
eval "$(shadowenv init zsh)"
@endif
@if ($shell === 'fish')
shadowenv init fish | source
@endif
