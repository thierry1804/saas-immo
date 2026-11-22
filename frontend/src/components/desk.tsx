import Link from 'next/link';

export type DeskLine = {
  href: string;
  label: string;
  detail: string;
  tone?: 'hot' | 'alert';
};

export function Desk({
  title,
  lines,
  children,
}: {
  title: string;
  lines: DeskLine[];
  children?: React.ReactNode;
}) {
  return (
    <div className="desk">
      <Link className="mark" href="/">
        Veille
      </Link>
      <h1>{title}</h1>
      {children}
      <ul className="lines">
        {lines.map((line) => (
          <li key={line.href + line.label}>
            <Link href={line.href}>
              <span>{line.label}</span>
              <small className={line.tone ?? ''}>{line.detail}</small>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
