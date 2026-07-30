import { existsSync, readFileSync, writeFileSync, appendFileSync } from 'node:fs'
import { UserException } from '../exceptions.js'
import { shadowLispString } from './shadow-lisp.js'

export class ShadowLispWriter {
  constructor(private readonly path: string) {
    if (!existsSync(this.path)) {
      throw new UserException(`File ${this.path} does not exist for writing`)
    }
  }

  envSet(key: string, value: string): void {
    const keyLiteral = shadowLispString(key)
    const line = `(env/set ${keyLiteral} ${shadowLispString(value)})`
    const regex = new RegExp(`\\(env/set ${escapeRegExp(keyLiteral)} .*\\)`, 'gm')
    const content = this.content()

    if (!regex.test(content)) {
      this.append(line)
      return
    }

    this.putContent(content.replace(regex, () => line))
  }

  prependPath(path: string): void {
    const pathKeyLiteral = shadowLispString('PATH')
    const pathLiteral = shadowLispString(path)
    const regex = new RegExp(
      `\\(env/prepend-to-pathlist ${escapeRegExp(pathKeyLiteral)} ${escapeRegExp(pathLiteral)}\\)`,
      'gm',
    )
    const content = this.content()

    if (regex.test(content)) return

    this.append(`(env/prepend-to-pathlist ${pathKeyLiteral} ${pathLiteral})`)
  }

  private append(line: string): void {
    appendFileSync(this.path, line + '\n')
  }

  private putContent(content: string): void {
    writeFileSync(this.path, content)
  }

  private content(): string {
    return readFileSync(this.path, 'utf8')
  }
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
