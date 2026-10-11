<?php
/**
 * File containing the expAiProjectHistory class.
 *
 * @copyright Copyright (C) 1998 - 2026 7x & Exponential Foundation. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expai
 */

/**
 * The history behind project_history: commits of local git clones ([HistoryRepository_<id>] Path, under one of
 * [HistorySettings] Roots[]) and release notes of public GitHub repositories ([HistoryRepository_<id>] GitHub,
 * read without a token from the GitHub API, cached ReleasesCacheTTL seconds).
 *
 * What never comes out: a path filter that matches [HistorySettings] ExcludePaths[] or is not tracked by git (git
 * log reads committed history only, never the working tree); commit titles and release note lines that name an
 * excluded directory or a root *.md file that git does not track; author names unless ShowAuthors=enabled.
 */
class expAiProjectHistory
{
    /** @var callable|null tests: function( $url ) => array( status, body ) instead of the GitHub API */
    public static $githubTransport = null;

    /** @var expAiConfig */
    protected $config;

    /** @var expAiGitRunner */
    protected $git;

    public function __construct( expAiConfig $config, ?expAiGitRunner $git = null )
    {
        $this->config = $config;
        $this->git = $git ?: new expAiGitRunner( (string) $config->get( 'HistorySettings', 'GitBinary', '/usr/bin/git' ),
                                                  $config->int( 'HistorySettings', 'Timeout', 10, 1, 60 ) );
    }

    /** @return array id => array( id, label, path (checked absolute, '' when none), github ('' when none), branch ) */
    public function repositories()
    {
        $out = array();
        foreach ( $this->config->arr( 'HistorySettings', 'Repositories' ) as $id )
        {
            if ( !preg_match( '/^[a-z0-9_]{1,30}$/', $id ) || !$this->config->ini()->hasGroup( 'HistoryRepository_' . $id ) )
                continue;
            $group = 'HistoryRepository_' . $id;
            $github = trim( (string) $this->config->get( $group, 'GitHub', '' ) );
            $out[$id] = array( 'id' => $id, 'label' => (string) $this->config->get( $group, 'Label', $id ),
                               'path' => (string) $this->config->get( $group, 'Path', '' ),
                               'github' => preg_match( '~^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$~', $github ) ? $github : '',
                               'branch' => (string) $this->config->get( $group, 'Branch', '' ) );
        }
        return $out;
    }

    /**
     * @param string $path as configured (absolute, or relative to the installation root)
     * @return string the real path of the clone
     * @throws expAiException when it is not a git clone under one of Roots[]
     */
    public function checkedPath( $path )
    {
        $root = rtrim( eZSys::rootDir(), '/' );
        $absolute = $path !== '' && $path[0] === '/' ? $path : $root . '/' . $path;
        $real = realpath( $absolute );
        if ( $real === false || !is_dir( $real ) || !file_exists( $real . '/.git' ) )
            throw new expAiException( 'The project history is not available for this repository.', 'history: not a git clone: ' . $path );
        foreach ( $this->config->arr( 'HistorySettings', 'Roots' ) as $allowed )
        {
            $allowed = $allowed[0] === '/' ? $allowed : $root . '/' . $allowed;
            $allowedReal = realpath( $allowed );
            if ( $allowedReal !== false && ( $real === $allowedReal || strpos( $real . '/', rtrim( $allowedReal, '/' ) . '/' ) === 0 ) )
                return $real;
        }
        throw new expAiException( 'The project history is not available for this repository.', 'history: the clone is outside [HistorySettings] Roots[]: ' . $path );
    }

    /** @return bool the path (relative to the repository) matches ExcludePaths[] */
    public function excludedPath( $path )
    {
        $path = trim( (string) $path, '/' );
        foreach ( $this->config->arr( 'HistorySettings', 'ExcludePaths' ) as $pattern )
        {
            $pattern = trim( (string) $pattern, '/' );
            if ( $pattern === '' )
                continue;
            if ( fnmatch( $pattern, $path, FNM_PATHNAME ) || fnmatch( $pattern, $path ) )
                return true;
            // a directory pattern (ai/*) covers the directory itself and everything below it
            if ( substr( $pattern, -2 ) === '/*' && ( $path === substr( $pattern, 0, -2 ) || strpos( $path . '/', substr( $pattern, 0, -1 ) ) === 0 ) )
                return true;
        }
        return false;
    }

    /**
     * @param string $path a path filter (relative to the repository)
     * @return string the clean path
     * @throws expAiException
     */
    public function checkedFilterPath( $repository, $path )
    {
        $path = trim( str_replace( '\\', '/', (string) $path ) );
        $path = preg_replace( '~^\./~', '', $path );
        if ( $path === '' || strlen( $path ) > 300 || $path[0] === '/' || $path[0] === '-' || $path[0] === ':' || preg_match( '~(^|/)\.\.(/|$)~', $path )
             || preg_match( '/[\x00-\x1f\x7f*?\[]/', $path ) )
            throw new expAiException( 'The path filter %path is not valid: give a path relative to the repository, without wildcards.', 'history: invalid path', 0, array( '%path' => expAiText::cut( $path, 100 ) ) );
        if ( $this->excludedPath( $path ) )
            throw new expAiException( 'The path %path is not part of the public project history.', 'history: excluded path', 0, array( '%path' => expAiText::cut( $path, 100 ) ) );
        // tracked files only: an untracked file (a private note) is never named, read or confirmed
        $tracked = trim( $this->git->run( $repository, array( 'ls-files', '-z', '--', rtrim( $path, '/' ) ) ) );
        if ( $tracked === '' )
            throw new expAiException( 'The path %path is not part of the public project history.', 'history: untracked path', 0, array( '%path' => expAiText::cut( $path, 100 ) ) );
        return rtrim( $path, '/' );
    }

    /** @return string[] lower-case words that may not appear in a title: excluded directories, untracked root *.md */
    protected function hiddenNames( $repository )
    {
        $names = array();
        foreach ( $this->config->arr( 'HistorySettings', 'ExcludePaths' ) as $pattern )
        {
            $pattern = trim( (string) $pattern, '/' );
            if ( substr( $pattern, -2 ) === '/*' && strpbrk( substr( $pattern, 0, -2 ), '*?[' ) === false )
                $names[] = substr( $pattern, 0, -1 );
        }
        if ( $this->config->flag( 'HistorySettings', 'HideUntrackedRootMarkdown', true ) )
        {
            $files = array();
            foreach ( (array) glob( $repository . '/*.md' ) as $file )
                $files[] = basename( $file );
            if ( $files )
            {
                $tracked = explode( "\0", $this->git->run( $repository, array_merge( array( 'ls-files', '-z', '--' ), $files ) ) );
                foreach ( array_diff( $files, $tracked ) as $name )
                    $names[] = $name;
            }
        }
        return $names;
    }

    /** @return bool the text names a hidden directory or file */
    public static function namesHidden( $text, array $names )
    {
        foreach ( $names as $name )
            if ( preg_match( '~(^|[^A-Za-z0-9_.-])' . preg_quote( $name, '~' ) . ( substr( $name, -1 ) === '/' ? '' : '($|[^A-Za-z0-9_-])' ) . '~i', (string) $text ) )
                return true;
        return false;
    }

    /**
     * @param array $repo a row of repositories()
     * @param array $filters since (YYYY-MM-DD), path, search, limit
     * @return array list of array( hash, date, title [, author] )
     * @throws expAiException
     */
    public function commits( array $repo, array $filters )
    {
        $path = $this->checkedPath( $repo['path'] );
        $limit = max( 1, min( $this->config->int( 'HistorySettings', 'MaxCommits', 30, 1, 200 ), (int) ( isset( $filters['limit'] ) ? $filters['limit'] : 10 ) ) );
        // %x1f between fields, %x1e after each commit: nothing in a title can break the format
        $arguments = array( 'log', '--no-color', '--no-merges', '--format=%h%x1f%cs%x1f%an%x1f%s%x1e', '--max-count=' . ( $limit * 3 + 10 ) );
        if ( !empty( $filters['since'] ) )
            $arguments[] = '--since=' . $filters['since'];
        if ( isset( $filters['search'] ) && $filters['search'] !== '' )
        {
            $arguments[] = '--regexp-ignore-case';
            $arguments[] = '--fixed-strings';
            $arguments[] = '--grep=' . $filters['search'];
        }
        $branch = $repo['branch'];
        if ( $branch !== '' && !preg_match( '~^(?!-)(?!.*\.\.)(?!.*//)[A-Za-z0-9._/-]{1,100}$~', $branch ) )
            throw new expAiException( 'The project history is not available for this repository.', 'history: invalid Branch setting' );
        $arguments[] = '--end-of-options';
        $arguments[] = $branch !== '' ? $branch : 'HEAD';
        if ( isset( $filters['path'] ) && $filters['path'] !== '' )
        {
            $arguments[] = '--';
            $arguments[] = $this->checkedFilterPath( $path, $filters['path'] );
        }
        $hidden = $this->hiddenNames( $path );
        $authors = $this->config->flag( 'HistorySettings', 'ShowAuthors', false );
        $out = array();
        foreach ( explode( "\x1e", $this->git->run( $path, $arguments ) ) as $record )
        {
            $fields = explode( "\x1f", trim( $record, "\n" ) );
            if ( count( $fields ) < 4 )
                continue;
            list( $hash, $date, $author, $title ) = $fields;
            if ( self::namesHidden( $title, $hidden ) )
                continue;
            $row = array( 'hash' => $hash, 'date' => $date, 'title' => expAiText::cut( $title, 300 ) );
            if ( $authors )
                $row['author'] = expAiText::cut( $author, 80 );
            $out[] = $row;
            if ( count( $out ) >= $limit )
                break;
        }
        return $out;
    }

    /**
     * @param array $repo a row of repositories() with a GitHub repository
     * @param array $filters since, search, limit
     * @return array list of array( tag, name, date, url, notes )
     * @throws expAiException
     */
    public function releases( array $repo, array $filters )
    {
        $limit = max( 1, min( 20, (int) ( isset( $filters['limit'] ) ? $filters['limit'] : 5 ) ) );
        $hidden = $repo['path'] !== '' ? $this->hiddenNamesSafe( $repo['path'] ) : $this->hiddenNamesConfigured();
        $out = array();
        foreach ( $this->githubReleases( $repo['github'] ) as $release )
        {
            if ( !is_array( $release ) || !empty( $release['draft'] ) )
                continue;
            $date = substr( (string) ( isset( $release['published_at'] ) ? $release['published_at'] : '' ), 0, 10 );
            if ( !empty( $filters['since'] ) && $date !== '' && $date < $filters['since'] )
                continue;
            $name = expAiText::oneLine( isset( $release['name'] ) && $release['name'] !== '' ? $release['name'] : ( isset( $release['tag_name'] ) ? $release['tag_name'] : '' ) );
            $lines = array();
            foreach ( preg_split( '/\r?\n/', (string) ( isset( $release['body'] ) ? $release['body'] : '' ) ) as $line )
                if ( !self::namesHidden( $line, $hidden ) )
                    $lines[] = $line;
            $notes = trim( implode( "\n", $lines ) );
            if ( isset( $filters['search'] ) && $filters['search'] !== '' && stripos( $name . "\n" . $notes, $filters['search'] ) === false )
                continue;
            $url = isset( $release['html_url'] ) && strpos( (string) $release['html_url'], 'https://github.com/' ) === 0 ? (string) $release['html_url'] : '';
            $out[] = array( 'tag' => expAiText::cut( (string) ( isset( $release['tag_name'] ) ? $release['tag_name'] : '' ), 60 ), 'name' => expAiText::cut( $name, 200 ),
                            'date' => $date, 'url' => $url,
                            'notes' => expAiText::cut( $notes, $this->config->int( 'HistorySettings', 'MaxReleaseNotesChars', 1500, 100, 20000 ) ) );
            if ( count( $out ) >= $limit )
                break;
        }
        return $out;
    }

    /** @return string[] hiddenNames() of a configured clone; the configured directories when it cannot be read */
    protected function hiddenNamesSafe( $path )
    {
        try
        {
            return $this->hiddenNames( $this->checkedPath( $path ) );
        }
        catch ( expAiException $e )
        {
            return $this->hiddenNamesConfigured();
        }
    }

    /** @return string[] the directories of ExcludePaths[] */
    protected function hiddenNamesConfigured()
    {
        $names = array();
        foreach ( $this->config->arr( 'HistorySettings', 'ExcludePaths' ) as $pattern )
        {
            $pattern = trim( (string) $pattern, '/' );
            if ( substr( $pattern, -2 ) === '/*' )
                $names[] = substr( $pattern, 0, -1 );
        }
        return $names;
    }

    /**
     * @param string $repository owner/name
     * @return array the releases as the GitHub API returns them (cached)
     * @throws expAiException
     */
    public function githubReleases( $repository )
    {
        $ttl = $this->config->int( 'HistorySettings', 'ReleasesCacheTTL', 3600, 0, 604800 );
        $dir = eZSys::cacheDirectory() . '/expai/history';
        $file = $dir . '/releases-' . sha1( $repository ) . '.json';
        if ( !self::$githubTransport && $ttl > 0 && is_file( $file ) && filemtime( $file ) > time() - $ttl )
        {
            $cached = json_decode( (string) @file_get_contents( $file ), true );
            if ( is_array( $cached ) )
                return $cached;
        }
        $url = rtrim( (string) $this->config->get( 'HistorySettings', 'GitHubApi', 'https://api.github.com' ), '/' ) . '/repos/' . $repository . '/releases?per_page=20';
        if ( self::$githubTransport )
            list( $status, $body ) = call_user_func( self::$githubTransport, $url );
        else
        {
            $parts = expAiWebUrl::parse( $url );
            if ( !$parts || $parts['scheme'] !== 'https' )
                throw new expAiException( 'The release notes could not be read.', 'history: GitHubApi must be https' );
            list( $pin ) = ( new expAiWebGuard() )->check( $parts );
            $http = new expAiHttpClient( $this->config->int( 'HistorySettings', 'Timeout', 10, 1, 60 ), 5, 2097152, array( 'retry-after', 'x-ratelimit-*' ) );
            list( $status, $body ) = $http->request( $url, array( 'X-GitHub-Api-Version: 2022-11-28' ), '',
                                                     array( 'method' => 'GET', 'accept' => 'application/vnd.github+json', 'resolve' => $pin ) );
        }
        if ( (int) $status === 404 )
            throw new expAiException( 'The releases of %repository are not public.', 'history: GitHub 404', 404, array( '%repository' => $repository ) );
        if ( (int) $status === 403 || (int) $status === 429 )
            throw new expAiException( 'GitHub limits requests without an account; the release notes are not available right now.', 'history: GitHub HTTP ' . $status, (int) $status );
        if ( (int) $status < 200 || (int) $status >= 300 )
            throw new expAiException( 'The release notes could not be read.', 'history: GitHub HTTP ' . $status, (int) $status );
        $releases = json_decode( (string) $body, true );
        if ( !is_array( $releases ) )
            throw new expAiException( 'The release notes could not be read.', 'history: GitHub answer is not JSON' );
        // only the fields used are cached
        $keep = array();
        foreach ( $releases as $release )
            if ( is_array( $release ) )
                $keep[] = array_intersect_key( $release, array_flip( array( 'tag_name', 'name', 'published_at', 'html_url', 'body', 'draft', 'prerelease' ) ) );
        if ( !self::$githubTransport && $ttl > 0 )
        {
            if ( !is_dir( $dir ) )
                eZDir::mkdir( $dir, false, true );
            eZFile::create( basename( $file ), $dir, json_encode( $keep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), true );
        }
        return $keep;
    }
}
